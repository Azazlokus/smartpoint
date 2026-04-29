# SmartPoint — Система мониторинга блогов про котов

Backend-сервис на Laravel 11, который периодически опрашивает источники блогов, синхронизирует посты и ведёт журнал мониторинга. Рассчитан на десятки тысяч блогов с индивидуальной настройкой частоты проверки (4–8 часов).

---

## Подход к реализации и выбор инструментов

### Общая идея

Система построена вокруг одного инварианта: **каждый блог знает, когда его нужно проверить следующий раз** (`next_check_at`). Планировщик раз в минуту диспатчит задачи для блогов, у которых это время наступило. Так частота проверки индивидуальна для каждого блога, а центральный координатор не нужен.

```
Scheduler (1 мин)
    └─▶ DispatchMonitoringJobs
            └─▶ MonitorBlogJob × N   (очередь "monitoring", Horizon)
                    └─▶ MonitoringService
                            ├─▶ AdapterFactory → конкретный адаптер (mock / wp / ...)
                            ├─▶ upsert posts в одну SQL-операцию
                            ├─▶ MonitoringLog (новые посты)
                            └─▶ blog.next_check_at += frequency_hours
```

### Почему Laravel 11

Задача — HTTP API + очереди + планировщик + БД. Laravel закрывает всё это из коробки: Eloquent, Queue, Scheduler, Horizon — без написания клея между компонентами. Альтернатива (Symfony + отдельный воркер + cron) потребовала бы значительно больше инфраструктурного кода без выигрыша в контроле.

### Почему очереди + Horizon, а не прямой вызов

Без очередей тысячи HTTP-запросов к внешним источникам блокировали бы друг друга. Каждый `MonitorBlogJob` независим: его можно повторить при ошибке (`tries=3`, экспоненциальный backoff), а Horizon даёт UI для мониторинга и управления воркерами. `ShouldBeUnique` гарантирует, что один блог не попадёт в очередь дважды одновременно.

### Паттерн Adapter для источников

Все внешние источники за одним интерфейсом (`BlogSourceAdapter`). Добавить новый источник = написать один класс и одну строку в `config/monitoring.php`. Остальной код не меняется. `AdapterFactory` резолвит нужную реализацию по `slug` ресурса.

### Job Middleware ThrottleBySource

Разные блоги могут принадлежать одному источнику. Чтобы не перегружать один API десятками параллельных запросов, мидлвар через `RateLimiter` ограничивает нагрузку: не более 5 запросов в 60 секунд на источник. Если лимит исчерпан — задача возвращается в очередь через 30 секунд (`release(30)`), а не падает.

### upsert вместо foreach+save

Синхронизация постов делается одним `DB::table('posts')->upsert(...)`. При тысячах постов это N+1 → 1 запрос в БД: меньше нагрузки, атомарность через транзакцию.

### spatie/laravel-query-builder для API

Фильтрация, сортировка и пагинация в `GET /api/v1/blogs` реализованы через эту библиотеку, а не вручную. Она обрабатывает `filter[name]`, `filter[rating_from]`, `sort=rating_desc` безопасно (только разрешённые поля), что исключает SQL-инъекции через параметры сортировки.

### Мониторинг здоровья блогов

`monitoring_failures` и `last_monitored_at` на модели `Blog` позволяют операционной команде видеть, какие блоги системно падают (например, источник вернул ошибку 3 раза подряд). ELK-стек (Elasticsearch + Logstash + Kibana) принимает структурированные JSON-логи и даёт возможность строить алерты по этим метрикам без разбора текстовых файлов.

### Инструменты качества

| Инструмент | Зачем |
|---|---|
| **Laravel Pint** | Единый стиль кода, `declare(strict_types=1)` везде — автоматически |
| **PHPStan уровень 6** | Ловит ошибки типов до рантайма; уровень 6 — баланс между строгостью и шумом |
| **PHPUnit + Feature-тесты** | Каждый слой (HTTP, Service, Adapter) покрыт тестами с реальной SQLite-БД |
| **GitHub Actions** | Pint + PHPStan + тесты на каждый пуш; PR в `main` невозможен при красном CI |

---

## Архитектура и паттерны

### Паттерн Adapter (источники данных)

Каждый внешний провайдер блогов изолирован за интерфейсом `BlogSourceAdapter` (`app/Adapters/BlogSourceAdapter.php`). Интерфейс объявляет два метода:

| Метод | Возвращает |
|---|---|
| `fetchBlogMeta(string $externalId)` | `BlogMetaDTO` |
| `fetchPosts(string $externalId)` | `PostDTO[]` |

В поставке — единственная реализация `MockAdapter`, возвращающая фиктивные данные. Реальные адаптеры (например, `WordPressAdapter`, `MediumAdapter`) реализуют тот же интерфейс.

`AdapterFactory` резолвит нужный адаптер по полю `slug` из таблицы `resources`, что делает добавление нового источника тривиальным:

```php
// AdapterFactory::$adapters
'mock'      => MockAdapter::class,
'wordpress' => WordPressAdapter::class, // будущее расширение
```

### DTO

| DTO | Поля |
|---|---|
| `BlogMetaDTO` | `name`, `rating`, `catName`, `author` |
| `PostDTO` | `externalId`, `title`, `body`, `rating`, `reactions[]` |

DTO — иммутабельные объекты-значения (свойства `readonly`), передающие данные между слоем адаптеров и доменным слоем без связывания.

### MonitoringService

`app/Services/MonitoringService.php` содержит основную логику одного цикла мониторинга:

1. Резолвит адаптер через `AdapterFactory`.
2. Получает обновлённые метаданные блога → `blog->update(...)`.
3. Получает посты источника → один вызов `DB::table('posts')->upsert(...)` (без `foreach+save`).
4. Удаляет посты, исчезнувшие из источника.
5. Записывает `MonitoringLog` со списком новых постов.

### MonitorBlogJob

`app/Jobs/MonitorBlogJob.php` оборачивает `MonitoringService::monitor()` в очередную задачу:

- Очередь: `monitoring`
- `tries = 3`, backoff: `60 / 120 / 300` секунд
- После успеха **или** финальной ошибки `next_check_at` сдвигается на `monitor_frequency_hours` вперёд.

### DispatchMonitoringJobs (консольная команда)

```
php artisan monitoring:dispatch
```

Запускается каждую минуту через Laravel Scheduler. Это «сторож»: он выбирает только те блоги, у которых `next_check_at <= now()`, и диспатчит по одному `MonitorBlogJob` через `chunkById(100)`. Благодаря `next_check_at` реальная частота обращения к каждому блогу составляет 4–8 часов — частый запуск команды лишь обеспечивает точность планирования.

### AddBlogToMonitoring (консольная команда)

```
php artisan blog:add {resource_slug} {external_id} [--frequency=4]
```

Добавляет блог на мониторинг из командной строки.

| Аргумент / опция | Описание |
|---|---|
| `resource_slug` | Slug источника (например: `mock`) |
| `external_id` | ID блога на стороне источника |
| `--frequency` | Частота мониторинга в часах (4–8, по умолчанию 4) |

**Примеры:**

```bash
# Добавить блог с дефолтной частотой (4 ч)
php artisan blog:add mock blog_42

# Добавить с частотой 8 часов
php artisan blog:add mock blog_42 --frequency=8
```

Команда завершается с кодом ошибки (exit 1) если:
- источник с указанным slug не найден или неактивен;
- блог уже на мониторинге;
- указана недопустимая частота (меньше 4 или больше 8).

### REST API — управление блогами

Базовый URL: `/api/blogs`

| Метод | Путь | Описание |
|---|---|---|
| `GET` | `/api/blogs` | Список всех блогов (пагинация, 50 на страницу) |
| `POST` | `/api/blogs` | Добавить блог на мониторинг |
| `GET` | `/api/blogs/{id}` | Детали одного блога |
| `DELETE` | `/api/blogs/{id}` | Снять блог с мониторинга |

**POST /api/blogs** — тело запроса (JSON):

```json
{
  "resource_id": 1,
  "external_id": "blog_42",
  "monitor_frequency_hours": 4
}
```

Поле `monitor_frequency_hours` принимает значения от 4 до 8. При успехе возвращается `201 Created` с объектом блога.

**Пример ответа:**

```json
{
  "data": {
    "id": 1,
    "external_id": "blog_42",
    "name": "blog_42",
    "rating": 0,
    "cat_name": null,
    "author": null,
    "monitor_frequency_hours": 4,
    "next_check_at": "2026-04-26T12:00:00+00:00",
    "resource": {
      "id": 1,
      "name": "Mock Source",
      "slug": "mock"
    }
  }
}
```

### Планировщик

Объявлен в `routes/console.php`:

```php
Schedule::command(DispatchMonitoringJobs::class)->everyMinute();
```

В Docker выделенный контейнер `scheduler` запускает `php artisan schedule:work`.

### Схема базы данных

```
resources         id | name | slug | url | description | is_active
blogs             id | resource_id(fk) | external_id | name | rating | cat_name | author
                     | monitor_frequency_hours | next_check_at
posts             id | blog_id(fk) | external_id | title | body | rating | reactions(json)
monitoring_logs   id | blog_id(fk) | date | new_posts(json)
```

---

## Быстрый старт

### 1. Окружение

```bash
cp .env.example .env
# Укажите DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
```

### 2. Docker

```bash
docker compose up -d
```

| Сервис | URL |
|---|---|
| Приложение (Nginx) | http://localhost:8000 |
| Adminer (GUI базы данных) | http://localhost:8080 |
| Laravel Horizon | http://localhost:8000/horizon |
| Laravel Telescope | http://localhost:8000/telescope |

### 3. Миграции и сидеры

```bash
docker compose exec app php artisan migrate --seed
```

Создаёт все таблицы и наполняет БД: 1 mock-ресурс + 10 тестовых блогов, готовых к мониторингу.

### 4. Ручной запуск диспатчера (опционально)

```bash
docker compose exec app php artisan monitoring:dispatch
```

---

## Качество кода

### Laravel Pint (стиль кода)

```bash
./vendor/bin/pint
```

Конфиг: `pint.json` — пресет `laravel`, принудительно добавляет `declare(strict_types=1)`.

### Larastan / PHPStan (статический анализ)

```bash
./vendor/bin/phpstan analyse
```

Конфиг: `phpstan.neon` — уровень **6**, анализирует директорию `app/`.

---

## Laravel Horizon

Horizon управляет очередью `monitoring` через супервизор `supervisor-monitoring`: **10 воркер-процессов** (production) / **3** (local). Конфигурация в `config/horizon.php`.

Дашборд: **http://localhost:8000/horizon**

---

## Laravel Telescope

Telescope подключён для локальной отладки. Записывает запросы, задачи очередей, SQL-запросы, исключения и многое другое. По умолчанию отключён в production (`APP_ENV=production`).

Дашборд: **http://localhost:8000/telescope**

---

## Запуск тестов

```bash
docker compose exec app php artisan test
```

---

## Структура проекта (кастомный код)

```
app/
  Adapters/
    BlogSourceAdapter.php   # интерфейс адаптера
    MockAdapter.php         # мок-реализация
    AdapterFactory.php      # резолвер slug → адаптер
  DTO/
    BlogMetaDTO.php
    PostDTO.php
  Models/
    Resource.php
    Blog.php
    Post.php
    MonitoringLog.php
  Services/
    MonitoringService.php   # основная логика мониторинга
  Jobs/
    MonitorBlogJob.php      # очередная задача (queue=monitoring, tries=3)
  Console/Commands/
    DispatchMonitoringJobs.php
    AddBlogToMonitoring.php
  Http/
    Controllers/Api/
      BlogController.php       # GET|POST /api/blogs, GET|DELETE /api/blogs/{id}
    Requests/
      StoreBlogRequest.php
    Resources/
      BlogResource.php
database/
  migrations/               # resources, blogs, posts, monitoring_logs
  seeders/                  # ResourceSeeder, BlogSeeder
routes/
  api.php                   # REST API маршруты (/api/blogs)
  console.php               # определение планировщика (каждую минуту)
config/
  horizon.php               # конфигурация супервизоров Horizon
.docker/
  nginx/default.conf
Dockerfile
docker-compose.yml
pint.json
phpstan.neon
```
