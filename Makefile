# Run the app and the tests in Docker, no local PHP needed. Type "make" for the list of commands.

export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)
RUN := docker compose run --rm app

.DEFAULT_GOAL := help
.PHONY: help start install db up down logs test shell

help: ## Show this help
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  make %-8s %s\n", $$1, $$2}'

start: install db up ## Install everything and start the app on http://localhost:8000

install: ## Build the PHP image and install the Composer dependencies
	docker compose build
	$(RUN) composer install

db: ## Create or update the database schema
	$(RUN) php bin/console doctrine:schema:update --force

up: ## Start the app on http://localhost:8000 (APP_PORT=8001 make up for another port)
	docker compose up -d
	@echo "App running on http://localhost:$${APP_PORT:-8000}"

down: ## Stop the app
	docker compose down

logs: ## Follow the web server logs
	docker compose logs -f app

test: ## Run the test suite
	$(RUN) vendor/bin/phpunit

shell: ## Open a shell in the PHP container
	$(RUN) bash
