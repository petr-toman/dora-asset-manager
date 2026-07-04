.PHONY: up dev down initialize

up:
	docker compose down
	docker compose up -d --build

dev:
	docker compose down
	docker compose -f docker-compose.yml -f docker-compose.dev.yml up --build

down:
	docker compose down

initialize:
	docker compose down -v
	docker compose build --no-cache
	docker compose up -d
