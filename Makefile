# Dog School Management - Makefile
# Simplified commands for Docker and Laravel operations

.PHONY: help build up down restart logs shell composer artisan migrate test clean install

# Colors for output
BLUE := \033[0;34m
GREEN := \033[0;32m
YELLOW := \033[0;33m
NC := \033[0m # No Color

help: ## Show this help message
	@echo '$(BLUE)Dog School Management - Available Commands:$(NC)'
	@echo ''
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-20s$(NC) %s\n", $$1, $$2}'
	@echo ''

build: ## Build all Docker containers
	@echo '$(BLUE)Building Docker containers...$(NC)'
	docker-compose build --no-cache

up: ## Start all Docker containers
	@echo '$(BLUE)Starting Docker containers...$(NC)'
	docker-compose up -d

down: ## Stop all Docker containers
	@echo '$(BLUE)Stopping Docker containers...$(NC)'
	docker-compose down

restart: down up ## Restart all Docker containers

logs: ## Show logs from all containers
	docker-compose logs -f

logs-php: ## Show PHP container logs
	docker-compose logs -f php

logs-nginx: ## Show Nginx container logs
	docker-compose logs -f nginx

shell: ## Open shell in PHP container
	docker-compose exec php sh

shell-root: ## Open shell as root in PHP container
	docker-compose exec -u root php sh

composer: ## Run composer command (usage: make composer cmd="install")
	docker-compose exec php composer $(cmd)

composer-install: ## Install composer dependencies
	@echo '$(BLUE)Installing Composer dependencies...$(NC)'
	docker-compose exec php composer install --no-interaction --prefer-dist --optimize-autoloader

artisan: ## Run artisan command (usage: make artisan cmd="migrate")
	docker-compose exec php php artisan $(cmd)

migrate: ## Run database migrations
	@echo '$(BLUE)Running database migrations...$(NC)'
	docker-compose exec php php artisan migrate

migrate-fresh: ## Drop all tables and re-run migrations
	@echo '$(YELLOW)WARNING: This will drop all tables!$(NC)'
	docker-compose exec php php artisan migrate:fresh

migrate-refresh: ## Rollback and re-run migrations
	docker-compose exec php php artisan migrate:refresh

seed: ## Run database seeders
	docker-compose exec php php artisan db:seed

migrate-seed: ## Run migrations and seeders
	docker-compose exec php php artisan migrate --seed

migrate-fresh-seed: ## Fresh migration with seeders
	@echo '$(YELLOW)WARNING: This will drop all tables!$(NC)'
	docker-compose exec php php artisan migrate:fresh --seed

test: ## Run PHPUnit tests
	@echo '$(BLUE)Running tests...$(NC)'
	docker-compose exec php php artisan test

test-coverage: ## Run tests with coverage
	docker-compose exec php php artisan test --coverage

pest: ## Run Pest tests
	docker-compose exec php ./vendor/bin/pest

cache-clear: ## Clear all caches
	@echo '$(BLUE)Clearing caches...$(NC)'
	docker-compose exec php php artisan cache:clear
	docker-compose exec php php artisan config:clear
	docker-compose exec php php artisan route:clear
	docker-compose exec php php artisan view:clear

optimize: ## Optimize Laravel application
	@echo '$(BLUE)Optimizing application...$(NC)'
	docker-compose exec php php artisan config:cache
	docker-compose exec php php artisan route:cache
	docker-compose exec php php artisan view:cache
	docker-compose exec php php artisan optimize

key-generate: ## Generate application key
	docker-compose exec php php artisan key:generate

storage-link: ## Create storage symbolic link
	docker-compose exec php php artisan storage:link

queue-work: ## Start queue worker
	docker-compose exec php php artisan queue:work

tinker: ## Open Laravel Tinker
	docker-compose exec php php artisan tinker

db-cli: ## Open PostgreSQL CLI
	docker-compose exec postgres psql -U dog_school_user -d dog_school

redis-cli: ## Open Redis CLI
	docker-compose exec redis redis-cli

clean: ## Clean up containers, volumes, and caches
	@echo '$(YELLOW)Cleaning up...$(NC)'
	docker-compose down -v
	rm -rf backend/vendor
	rm -rf backend/storage/framework/cache/*
	rm -rf backend/storage/framework/sessions/*
	rm -rf backend/storage/framework/views/*
	rm -rf backend/storage/logs/*
	rm -rf frontend/node_modules
	rm -rf frontend/dist

install: build up composer-install key-generate migrate-seed ## Full installation
	@echo '$(GREEN)Installation complete!$(NC)'
	@echo '$(BLUE)Application is running at: http://localhost:8080$(NC)'
	@echo '$(BLUE)Frontend dev server: http://localhost:5173$(NC)'
	@echo '$(BLUE)Mailpit UI: http://localhost:8025$(NC)'

fresh-install: clean install ## Clean installation from scratch

ps: ## Show running containers
	docker-compose ps

stats: ## Show container stats
	docker stats

health: ## Check health of services
	@echo '$(BLUE)Checking service health...$(NC)'
	@docker-compose ps
	@echo ''
	@docker-compose exec postgres pg_isready -U dog_school_user
	@docker-compose exec redis redis-cli ping
