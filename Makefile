# Run the app and the tests in Docker, no local PHP needed. Type "make" for the list of commands.

export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)
RUN := docker compose run --rm app

.DEFAULT_GOAL := help
.PHONY: help start install db up down logs test stan cs cs-fix lint check shell

help: ## Show this help
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  make %-8s %s\n", $$1, $$2}'

start: install db up ## Install everything and start the app on http://localhost:8000

install: ## Build the PHP image and install the Composer dependencies
	docker compose build
	$(RUN) composer install

db: ## Create or update the database schema (runs the migrations)
	$(RUN) php bin/console doctrine:migrations:migrate --no-interaction

up: ## Start the app on http://localhost:8000 (APP_PORT=8001 make up for another port)
	docker compose up -d
	@echo "App running on http://localhost:$${APP_PORT:-8000}"

down: ## Stop the app
	docker compose down

logs: ## Follow the web server logs
	docker compose logs -f app

test: ## Run the test suite
	$(RUN) vendor/bin/phpunit

stan: ## Analyse the code with PHPStan
	$(RUN) sh -c 'php bin/console cache:warmup --env=test --quiet && vendor/bin/phpstan analyse --memory-limit=1G'

cs: ## Check the code style with PHP-CS-Fixer
	$(RUN) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Fix the code style
	$(RUN) vendor/bin/php-cs-fixer fix

lint: ## Check the templates, the config and the services
	$(RUN) sh -c 'php bin/console lint:twig templates && php bin/console lint:yaml config --parse-tags && php bin/console lint:container'

check: test stan cs lint ## Run the tests and the code checks, like the CI

shell: ## Open a shell in the PHP container
	$(RUN) bash
