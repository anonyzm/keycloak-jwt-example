up:
	docker compose up -d

deploy: down clean    
	docker compose build
	docker compose up -d --force-recreate
	docker compose exec --user application backend composer install

recreate:
	docker compose up -d --force-recreate

down:
	docker compose down

build:
	docker compose build --no-cache

rebuild:
	docker compose down
	docker compose build --no-cache
	docker compose up -d

logs:
	docker compose logs -f backend

bash:
	docker compose exec --user application backend bash

restart:
	docker compose restart

ps:
	docker compose ps

clean:
	docker system prune -f
	docker volume rm keycloak-jwt_keycloak-db-data
	docker volume prune -f

prune:
	docker-compose down -v
	docker system prune -a

composer-install:
	docker compose exec --user application backend composer install

composer-update:
	docker compose exec --user application backend composer update

test-guest-token:
	curl -X POST "http://localhost/api/auth/guest-token" -H "Content-Type: application/json"

test-protected-no-token:
	curl -X GET "http://localhost/api/protected/hello-world"

test-demo-endpoints:
	@echo "=== Тестирование новой системы авторизации ==="
	@echo "1. Получаем гостевой токен..."
	@GUEST_TOKEN=$$(curl -s -X POST "http://localhost/api/auth/guest-token" -H "Content-Type: application/json" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4); \
	echo "Гостевой токен: $$GUEST_TOKEN"; \
	echo "2. Тестируем эндпоинт для guest/user..."; \
	curl -X GET "http://localhost/api/demo/public" -H "Authorization: Bearer $$GUEST_TOKEN" | jq .; \
	echo "3. Тестируем эндпоинт только для user (должен отказать)..."; \
	curl -X GET "http://localhost/api/demo/user-only" -H "Authorization: Bearer $$GUEST_TOKEN" | jq .; \
	echo "4. Тестируем эндпоинт без ролей..."; \
	curl -X GET "http://localhost/api/demo/no-role" -H "Authorization: Bearer $$GUEST_TOKEN" | jq .;
