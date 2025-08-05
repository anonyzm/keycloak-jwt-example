up:
	docker compose up -d

deploy:
	docker compose build
	docker compose up -d --force-recreate
	docker compose exec --user application blog-app composer install

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
	docker volume prune -f

composer-install:
	docker compose exec --user application backend composer install

composer-update:
	docker compose exec --user application backend composer update
