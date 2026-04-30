# ER-диаграмма базы данных

```mermaid
erDiagram
    resources {
        bigint id PK
        string name
        string slug UK "уникальный идентификатор источника"
        string url
        text description
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    blogs {
        bigint id PK
        bigint resource_id FK
        string external_id "ID блога на стороне источника"
        string name
        float rating
        string cat_name "nullable"
        string author "nullable"
        tinyint monitor_frequency_hours "4–8 часов"
        timestamp next_check_at "когда запланирована следующая проверка"
        timestamp last_monitored_at "nullable — когда последний раз успешно проверен"
        tinyint monitoring_failures "счётчик последовательных ошибок"
        timestamp deleted_at "nullable — soft delete"
        timestamp created_at
        timestamp updated_at
    }

    posts {
        bigint id PK
        bigint blog_id FK
        string external_id "ID поста на стороне источника"
        string title
        text body
        float rating
        json reactions
        timestamp created_at
        timestamp updated_at
    }

    monitoring_logs {
        bigint id PK
        bigint blog_id FK
        datetime date "дата цикла мониторинга"
        json new_posts "список новых постов: [{external_id, title}]"
        timestamp created_at
        timestamp updated_at
    }

    resources ||--o{ blogs : "один источник — много блогов"
    blogs ||--o{ posts : "один блог — много постов"
    blogs ||--o{ monitoring_logs : "один блог — много записей лога"
```

## Ключевые решения схемы

| Поле | Причина |
|---|---|
| `next_check_at` | Каждый блог знает, когда его проверить — нет центрального координатора |
| `monitoring_failures` | Счётчик позволяет найти системно падающие блоги без разбора логов |
| `last_monitored_at` | Показывает «мёртвые» блоги, которые давно не мониторились |
| `deleted_at` | Soft delete сохраняет историю `monitoring_logs` после снятия с мониторинга |
| `posts.reactions` | JSON — структура реакций у каждого источника своя |
| `resources.slug` | Уникальный ключ для резолюции адаптера в `AdapterFactory` |
