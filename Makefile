APP = docker compose exec app

# ──────────────────────────────────────────────────────────────────────────────
# Docker
# ──────────────────────────────────────────────────────────────────────────────

.PHONY: up
up:
	docker compose up -d

.PHONY: down
down:
	docker compose down

.PHONY: restart
restart:
	docker compose restart

.PHONY: build
build:
	docker compose build --no-cache

.PHONY: logs
logs:
	docker compose logs -f

.PHONY: ps
ps:
	docker compose ps

# ──────────────────────────────────────────────────────────────────────────────
# Приложение
# ──────────────────────────────────────────────────────────────────────────────

.PHONY: install
install:
	$(APP) composer install

.PHONY: key
key:
	$(APP) php artisan key:generate

.PHONY: migrate
migrate:
	$(APP) php artisan migrate

.PHONY: migrate-fresh
migrate-fresh:
	$(APP) php artisan migrate:fresh --seed

.PHONY: seed
seed:
	$(APP) php artisan db:seed

.PHONY: cache-clear
cache-clear:
	$(APP) php artisan cache:clear
	$(APP) php artisan config:clear
	$(APP) php artisan route:clear
	$(APP) php artisan view:clear

.PHONY: tinker
tinker:
	$(APP) php artisan tinker

# ──────────────────────────────────────────────────────────────────────────────
# Мониторинг
# ──────────────────────────────────────────────────────────────────────────────

.PHONY: dispatch
dispatch:
	$(APP) php artisan monitoring:dispatch

.PHONY: horizon-pause
horizon-pause:
	$(APP) php artisan horizon:pause

.PHONY: horizon-continue
horizon-continue:
	$(APP) php artisan horizon:continue

.PHONY: horizon-terminate
horizon-terminate:
	$(APP) php artisan horizon:terminate

# ──────────────────────────────────────────────────────────────────────────────
# Качество кода
# ──────────────────────────────────────────────────────────────────────────────

.PHONY: pint
pint:
	$(APP) ./vendor/bin/pint

.PHONY: pint-check
pint-check:
	$(APP) ./vendor/bin/pint --test

.PHONY: stan
stan:
	$(APP) ./vendor/bin/phpstan analyse --memory-limit=512M

.PHONY: test
test:
	$(APP) php artisan test

.PHONY: test-coverage
test-coverage:
	$(APP) php artisan test --coverage

.PHONY: lint
lint: pint-check stan

.PHONY: ci
ci: pint-check stan test
	@echo "CI пройден успешно."

# Те же цели без Docker (для локального запуска и GitHub Actions)
.PHONY: pint-check-local
pint-check-local:
	./vendor/bin/pint --test

.PHONY: stan-local
stan-local:
	./vendor/bin/phpstan analyse --memory-limit=512M

.PHONY: test-local
test-local:
	php artisan test

.PHONY: ci-local
ci-local: pint-check-local stan-local test-local
	@echo "CI пройден успешно."

# ──────────────────────────────────────────────────────────────────────────────
# Утилиты
# ──────────────────────────────────────────────────────────────────────────────

.PHONY: shell
shell:
	$(APP) bash

.PHONY: setup
setup: up install key migrate seed
	@echo "Проект готов к работе."
