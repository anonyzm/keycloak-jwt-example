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
