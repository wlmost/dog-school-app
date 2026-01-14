#!/bin/bash
# Production Deployment Script for Shared Hosting
# Target: https://www.leisoft.de/dog-school
# Structure: dog-school/backend and dog-school/frontend

set -e  # Exit on error

echo "========================================="
echo "  Hundeschule - Production Deployment"
echo "========================================="
echo ""

# Configuration - ADJUST THESE PATHS!
DEPLOY_PATH="/path/to/dog-school"  # Change this to your actual path!
BACKEND_DIR="$DEPLOY_PATH/backend"
FRONTEND_DIR="$DEPLOY_PATH/frontend"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
function step() {
    echo -e "${GREEN}➜${NC} $1"
}

function error() {
    echo -e "${RED}✗${NC} $1"
    exit 1
}

function warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "$BACKEND_DIR/artisan" ]; then
    error "This script must be run from the project root directory!"
fi

# Step 1: Maintenance Mode
step "Activating maintenance mode..."
cd "$BACKEND_DIR"
php artisan down || warning "Could not activate maintenance mode (maybe already down?)"

# Step 2: Pull latest changes (if using Git on server)
if [ -d "$DEPLOY_PATH/.git" ]; then
    step "Pulling latest changes from repository..."
    cd "$DEPLOY_PATH"
    git pull origin master || warning "Git pull failed or not configured"
else
    warning "Not a git repository. Skipping git pull."
fi

# Step 3: Backend Dependencies
step "Installing backend dependencies..."
cd "$BACKEND_DIR"
composer install --no-dev --optimize-autoloader --no-interaction || error "Composer install failed"

# Step 4: Run Migrations
step "Running database migrations..."
php artisan migrate --force || error "Database migration failed"

# Step 5: Clear and Cache Config
step "Caching configuration..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 6: Frontend Build
step "Building frontend..."
cd "$FRONTEND_DIR"
npm ci --production || error "npm install failed"
npm run build || error "Frontend build failed"

# Step 7: Restart Queue Workers (if using Supervisor)
if command -v supervisorctl &> /dev/null; then
    step "Restarting queue workers..."
    sudo supervisorctl restart hundeschule-worker:* || warning "Could not restart workers (maybe not configured?)"
else
    warning "Supervisor not found. Skipping queue worker restart."
fi

# Step 8: Restart PHP-FPM (optional, uncomment if needed)
# step "Restarting PHP-FPM..."
# sudo systemctl restart php8.4-fpm || warning "Could not restart PHP-FPM"

# Step 9: Exit Maintenance Mode
step "Deactivating maintenance mode..."
cd "$BACKEND_DIR"
php artisan up

# Success
echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}  Deployment completed successfully!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo "Application URL: https://www.leisoft.de/dog-school"
echo ""

# Show deployed version (if git available)
if [ -d "$DEPLOY_PATH/.git" ]; then
    cd "$DEPLOY_PATH"
    COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")
    echo "Deployed version: $COMMIT"
    echo ""
fi

exit 0
