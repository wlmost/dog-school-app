#!/bin/bash
#
# HomoCanis Deployment Package Builder
# Creates a deployment-ready tar.gz archive for shared hosting
#
# Usage: ./build-deployment.sh
#

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
BUILD_DIR="build-deploy"
TIMESTAMP=$(date +"%Y%m%d-%H%M%S")
ARCHIVE_NAME="homocanis-deployment-${TIMESTAMP}.tar.gz"

# Error handling function
error_exit() {
    echo -e "${RED}ERROR: $1${NC}" >&2
    cleanup_on_failure
    exit 1
}

# Success message function
success_msg() {
    echo -e "${GREEN}✓ $1${NC}"
}

# Info message function
info_msg() {
    echo -e "${BLUE}→ $1${NC}"
}

# Warning message function
warn_msg() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

# Cleanup function for successful builds
cleanup_success() {
    if [ -d "$BUILD_DIR" ]; then
        info_msg "Cleaning up temporary build directory..."
        rm -rf "$BUILD_DIR"
        success_msg "Cleanup complete"
    fi
}

# Cleanup function for failed builds
cleanup_on_failure() {
    if [ -d "$BUILD_DIR" ]; then
        warn_msg "Cleaning up partial build artifacts..."
        rm -rf "$BUILD_DIR"
    fi
    if [ -f "$ARCHIVE_NAME" ]; then
        rm -f "$ARCHIVE_NAME"
    fi
}

# Check if required commands are available
check_dependencies() {
    info_msg "Checking dependencies..."
    
    local missing_deps=()
    
    if ! command -v docker &> /dev/null; then
        missing_deps+=("docker")
    fi
    
    if ! command -v rsync &> /dev/null; then
        missing_deps+=("rsync")
    fi
    
    if ! command -v tar &> /dev/null; then
        missing_deps+=("tar")
    fi
    
    if ! command -v gzip &> /dev/null; then
        missing_deps+=("gzip")
    fi
    
    if [ ${#missing_deps[@]} -ne 0 ]; then
        error_exit "Missing required dependencies: ${missing_deps[*]}\nPlease install them and try again."
    fi
    
    # Verify the required containers are running
    if ! docker inspect dog-school-php &> /dev/null; then
        error_exit "PHP container 'dog-school-php' is not running. Start it with: docker-compose up -d"
    fi
    
    if ! docker inspect dog-school-node &> /dev/null; then
        error_exit "Node container 'dog-school-node' is not running. Start it with: docker-compose up -d"
    fi
    
    success_msg "All dependencies available"
}

# Install backend production dependencies
install_backend_dependencies() {
    info_msg "Installing backend production dependencies (via docker)..."
    docker exec dog-school-php sh -c \
        "composer install --no-dev --optimize-autoloader --no-interaction --working-dir=/var/www/html" \
        || error_exit "Backend dependency installation failed"
    success_msg "Backend dependencies installed"
}

# Install frontend dependencies and build
install_frontend_dependencies() {
    info_msg "Installing frontend dependencies (via docker)..."
    docker exec dog-school-node sh -c "npm ci" \
        || error_exit "Frontend dependency installation failed"
    success_msg "Frontend dependencies installed"
}

# Build frontend assets
build_frontend() {
    info_msg "Building frontend assets (via docker)..."
    docker exec dog-school-node sh -c "npm run build:deploy" \
        || error_exit "Frontend build failed"
    success_msg "Frontend build complete"
}

# Verify production dependencies
verify_dependencies() {
    info_msg "Verifying production dependencies..."
    
    if [ ! -d "backend/vendor" ]; then
        error_exit "Backend vendor directory not found"
    fi
    
    if [ ! -d "frontend/dist" ]; then
        error_exit "Frontend dist directory not found"
    fi
    
    success_msg "Production dependencies verified"
}

# Create temporary build directory
create_build_directory() {
    info_msg "Creating temporary build directory..."
    
    # Clean up old build directory if it exists
    if [ -d "$BUILD_DIR" ]; then
        warn_msg "Removing old build directory..."
        rm -rf "$BUILD_DIR"
    fi
    
    mkdir -p "$BUILD_DIR" || error_exit "Failed to create build directory"
    success_msg "Build directory created: $BUILD_DIR"
}

# Copy application files with exclusions
copy_application_files() {
    info_msg "Copying application files..."
    
    # Copy backend directory with exclusions
    info_msg "Copying backend files..."
    rsync -a --exclude='node_modules' \
             --exclude='.git' \
             --exclude='.env' \
             --exclude='.env.*' \
             --exclude='tests' \
             --exclude='storage/logs/*' \
             --exclude='storage/framework/cache/*' \
             --exclude='storage/framework/sessions/*' \
             --exclude='storage/framework/views/*' \
             --exclude='._*' \
             --exclude='.DS_Store' \
             --exclude='__MACOSX' \
             backend/ "$BUILD_DIR/backend/" || error_exit "Failed to copy backend files"
    
    # Copy frontend dist
    info_msg "Copying frontend build assets..."
    mkdir -p "$BUILD_DIR/frontend/dist"
    rsync -a --exclude='._*' \
             --exclude='.DS_Store' \
             --exclude='__MACOSX' \
             frontend/dist/ "$BUILD_DIR/frontend/dist/" || error_exit "Failed to copy frontend dist"
    
    # Copy .env.example as .env.template
    info_msg "Copying .env.example as .env.template..."
    if [ -f "backend/.env.example" ]; then
        cp backend/.env.example "$BUILD_DIR/backend/.env.template" || error_exit "Failed to copy .env.example"
    else
        warn_msg ".env.example not found, skipping..."
    fi
    
    # Copy LICENSE and README if they exist
    [ -f "LICENSE" ] && cp LICENSE "$BUILD_DIR/"
    [ -f "README.md" ] && cp README.md "$BUILD_DIR/"

    # Copy update wizard and maintenance page
    info_msg "Adding update wizard and maintenance page..."
    cp update.php "$BUILD_DIR/update.php" || error_exit "Failed to copy update.php"
    cp maintenance.html "$BUILD_DIR/maintenance.html" || error_exit "Failed to copy maintenance.html"
    
    success_msg "Application files copied"
}

# Verify directory structure
verify_directory_structure() {
    info_msg "Verifying directory structure..."
    
    local required_paths=(
        "$BUILD_DIR/backend"
        "$BUILD_DIR/backend/public"
        "$BUILD_DIR/backend/app"
        "$BUILD_DIR/backend/vendor"
        "$BUILD_DIR/frontend/dist"
    )
    
    for path in "${required_paths[@]}"; do
        if [ ! -d "$path" ]; then
            error_exit "Required directory not found: $path"
        fi
    done
    
    success_msg "Directory structure verified"
}

# Copy and place .htaccess files
copy_htaccess_files() {
    info_msg "Copying .htaccess files..."
    
    local template_dir="deployment-templates/htaccess"
    
    if [ ! -d "$template_dir" ]; then
        error_exit ".htaccess templates directory not found: $template_dir"
    fi
    
    # Root .htaccess – use post-install version so both update.php and the SPA
    # work correctly when extracting over an existing installation.
    # install.php is also reachable via this file for fresh installs.
    info_msg "Placing root .htaccess..."
    cp "$template_dir/root-post-install.htaccess" "$BUILD_DIR/.htaccess" || error_exit "Failed to copy root .htaccess"
    
    # Backend public .htaccess
    info_msg "Placing backend/public .htaccess..."
    cp "$template_dir/backend-public.htaccess" "$BUILD_DIR/backend/public/.htaccess" || error_exit "Failed to copy backend/public .htaccess"
    
    # Backend root .htaccess (deny all)
    info_msg "Placing backend root .htaccess..."
    cp "$template_dir/backend-root.htaccess" "$BUILD_DIR/backend/.htaccess" || error_exit "Failed to copy backend .htaccess"
    
    # Storage .htaccess (deny all)
    info_msg "Placing storage .htaccess..."
    mkdir -p "$BUILD_DIR/backend/storage"
    cp "$template_dir/storage.htaccess" "$BUILD_DIR/backend/storage/.htaccess" || error_exit "Failed to copy storage .htaccess"
    
    # Frontend .htaccess (deny all)
    info_msg "Placing frontend .htaccess..."
    cp "$template_dir/frontend.htaccess" "$BUILD_DIR/frontend/.htaccess" || error_exit "Failed to copy frontend .htaccess"

    # Frontend dist .htaccess – sets proper caching headers (no-cache for index.html,
    # long-cache for content-hashed assets) so browsers always load fresh JS after deploy.
    info_msg "Placing frontend/dist .htaccess..."
    cp "$template_dir/frontend-dist.htaccess" "$BUILD_DIR/frontend/dist/.htaccess" || error_exit "Failed to copy frontend/dist .htaccess"
    
    success_msg ".htaccess files placed"
}

# Verify .htaccess files are in correct locations
verify_htaccess_files() {
    info_msg "Verifying .htaccess files..."
    
    local htaccess_files=(
        "$BUILD_DIR/.htaccess"
        "$BUILD_DIR/backend/public/.htaccess"
        "$BUILD_DIR/backend/.htaccess"
        "$BUILD_DIR/backend/storage/.htaccess"
        "$BUILD_DIR/frontend/.htaccess"
        "$BUILD_DIR/frontend/dist/.htaccess"
    )
    
    for file in "${htaccess_files[@]}"; do
        if [ ! -f "$file" ]; then
            error_exit ".htaccess file not found: $file"
        fi
    done
    
    success_msg "All .htaccess files verified"
}

# Copy installer script
copy_installer() {
    info_msg "Adding installation wizard..."
    
    if [ ! -f "install.php" ]; then
        error_exit "install.php not found in project root"
    fi
    
    cp install.php "$BUILD_DIR/install.php" || error_exit "Failed to copy install.php"
    chmod 755 "$BUILD_DIR/install.php"
    
    success_msg "Installation wizard added"
}

# Create tar.gz archive
create_archive() {
    info_msg "Creating deployment archive..."
    
    # COPYFILE_DISABLE=1 prevents macOS tar from embedding resource forks
    # as ._* AppleDouble files inside the archive.
    COPYFILE_DISABLE=1 tar -czf "$ARCHIVE_NAME" -C "$BUILD_DIR" \
        --exclude='._*' \
        --exclude='.DS_Store' \
        --exclude='__MACOSX' \
        . || error_exit "Failed to create archive"
    
    success_msg "Archive created: $ARCHIVE_NAME"
}

# Verify archive integrity
verify_archive() {
    info_msg "Verifying archive integrity..."
    
    # Test archive can be listed
    tar -tzf "$ARCHIVE_NAME" > /dev/null || error_exit "Archive integrity check failed"
    
    # Verify critical files are present
    info_msg "Verifying critical files in archive..."
    local critical_files=(
        "./backend/public/index.php"
        "./install.php"
        "./update.php"
        "./maintenance.html"
        "./.htaccess"
        "./backend/public/.htaccess"
    )
    
    for file in "${critical_files[@]}"; do
        if ! tar -tzf "$ARCHIVE_NAME" | grep -qF "$file"; then
            error_exit "Critical file missing from archive: $file"
        fi
    done
    
    success_msg "Archive integrity verified"
}

# Display archive information
display_archive_info() {
    local size=$(ls -lh "$ARCHIVE_NAME" | awk '{print $5}')
    local file_count=$(tar -tzf "$ARCHIVE_NAME" | wc -l | tr -d ' ')
    
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║        Build Completed Successfully!       ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BLUE}Archive:${NC}      $ARCHIVE_NAME"
    echo -e "${BLUE}Size:${NC}         $size"
    echo -e "${BLUE}Files:${NC}        $file_count"
    echo ""
    echo -e "${YELLOW}Next steps:${NC}"
    echo "  1. Upload $ARCHIVE_NAME to your shared hosting server"
    echo "  2. Extract the archive in your desired directory"
    echo "  3. Access install.php in your browser to complete installation"
    echo ""
}

# Main build process
main() {
    echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║  HomoCanis Deployment Package Builder     ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
    echo ""

    # Run dependency check
    check_dependencies
    
    echo ""
    echo -e "${BLUE}Starting build process...${NC}"
    echo ""
    
    # Step 1: Install dependencies
    install_backend_dependencies
    install_frontend_dependencies
    
    # Step 2: Build frontend
    build_frontend
    
    # Step 3: Verify dependencies
    verify_dependencies
    
    # Step 4: Create build directory
    create_build_directory
    
    # Step 5: Copy application files
    copy_application_files
    
    # Step 6: Verify directory structure
    verify_directory_structure
    
    # Step 7: Copy .htaccess files
    copy_htaccess_files
    
    # Step 8: Verify .htaccess files
    verify_htaccess_files
    
    # Step 8.5: Copy install.php
    copy_installer
    
    # Step 9: Create archive
    create_archive
    
    # Step 10: Verify archive
    verify_archive
    
    # Step 11: Display info
    display_archive_info
    
    # Step 12: Cleanup
    cleanup_success
}

# Run main function
main
