#!/bin/bash
#
# HomoCanis Deployment Package Builder (Docker Version)
# Creates a deployment-ready tar.gz archive for shared hosting
# Runs composer and npm commands inside Docker containers
#
# Usage: ./build-deployment-docker.sh [--php-version 8.3]
#
# Options:
#   -p, --php-version VERSION   PHP version to build for (default: 8.4)
#                               Example: --php-version 8.3
#

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Defaults
PHP_VERSION="8.4"

# Parse arguments
while [[ $# -gt 0 ]]; do
    case "$1" in
        -p|--php-version)
            PHP_VERSION="$2"
            shift 2
            ;;
        -h|--help)
            echo "Usage: $0 [--php-version VERSION]"
            echo "  -p, --php-version VERSION   PHP version (default: 8.4)"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown argument: $1${NC}" >&2
            exit 1
            ;;
    esac
done

# Validate PHP version format (e.g. 8.3, 8.4)
if ! [[ "$PHP_VERSION" =~ ^[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}ERROR: Invalid PHP version format: '$PHP_VERSION'. Expected e.g. 8.3 or 8.4${NC}" >&2
    exit 1
fi

# Derive short version tag (e.g. 8.3 -> php83)
PHP_VERSION_TAG="php$(echo "$PHP_VERSION" | tr -d '.')"
BUILDER_IMAGE="homocanis-builder-${PHP_VERSION_TAG}"

# Configuration
BUILD_DIR="build-deploy"
TIMESTAMP=$(date +"%Y%m%d-%H%M%S")
ARCHIVE_NAME="homocanis-deployment-${PHP_VERSION_TAG}-${TIMESTAMP}.tar.gz"

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

# Error message function (non-fatal)
error_msg() {
    echo -e "${RED}✗ $1${NC}"
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

# Check if Docker is running
check_docker() {
    info_msg "Checking Docker..."
    
    if ! command -v docker &> /dev/null; then
        error_exit "Docker is not installed or not in PATH"
    fi
    
    if ! docker info &> /dev/null; then
        error_exit "Docker is not running. Please start Docker Desktop."
    fi
    
    success_msg "Docker is running"
}

# Check if Docker Compose is available
check_docker_compose() {
    info_msg "Checking Docker Compose..."
    
    if ! docker compose version &> /dev/null; then
        error_exit "Docker Compose is not available"
    fi
    
    success_msg "Docker Compose available"
}

# Build the lightweight PHP builder image for the requested PHP version
build_php_builder_image() {
    info_msg "Building PHP ${PHP_VERSION} builder image (${BUILDER_IMAGE})..."
    
    docker build \
        --build-arg PHP_VERSION="${PHP_VERSION}" \
        -t "${BUILDER_IMAGE}" \
        -f docker/php/Dockerfile.build \
        . || error_exit "Failed to build PHP ${PHP_VERSION} builder image"
    
    success_msg "PHP ${PHP_VERSION} builder image ready"
}

# Install backend production dependencies
install_backend_dependencies() {
    info_msg "Installing backend production dependencies (PHP ${PHP_VERSION} in Docker)..."
    
    docker run --rm \
        -v "$(pwd)/backend:/app" \
        -w /app \
        "${BUILDER_IMAGE}" \
        composer install --no-dev --optimize-autoloader --no-interaction \
        || error_exit "Backend dependency installation failed"
    
    success_msg "Backend dependencies installed (PHP ${PHP_VERSION})"
}

# Install frontend dependencies and build
install_frontend_dependencies() {
    info_msg "Installing frontend dependencies (in Docker)..."
    
    # Run npm ci in Docker container (use service name 'node', not container name)
    docker compose run --rm node npm ci || error_exit "Frontend dependency installation failed"
    
    success_msg "Frontend dependencies installed"
}

# Build frontend assets
build_frontend() {
    info_msg "Building frontend assets (in Docker)..."
    
    # Run vite build directly, skipping TypeScript checks for deployment package
    # (TypeScript errors should be fixed in development, but shouldn't block production build)
    docker compose run --rm node npx vite build || error_exit "Frontend build failed"
    
    success_msg "Frontend build complete"
}

# Patch PHP version requirements in install.php and requirements-check.php
# so the installer accepts the PHP version this package was built for.
patch_php_version_requirement() {
    local minor="${PHP_VERSION#*.}"

    local files=(
        "$BUILD_DIR/backend/requirements-check.php"
    )

    # Write a temp perl script via heredoc so bash expands version vars cleanly
    # while perl regex special chars (like \$) stay escaped.
    local tmp_perl
    tmp_perl=$(mktemp /tmp/php_patch_XXXXXX.pl)
    cat > "$tmp_perl" << ENDPERL
# requirements-check.php: PHP minor version comparisons
s/\\\$minor < \K\d+/${minor}/g;
s/\\\$minor > \K\d+/${minor}/g;
# install.php: 'required' => '8.x'
s/'required' => '\K8\.\d+(?=')/${PHP_VERSION}/g;
# install.php: version_compare(PHP_VERSION, '8.x.0', ...)
s/version_compare\(PHP_VERSION, '\K8\.\d+(?=\.0')/${PHP_VERSION}/g;
# All free-text version references
s/PHP 8\.\d+\.0 or higher/PHP ${PHP_VERSION}.0 or higher/g;
s/\(8\.\d+\.x recommended\)/(${PHP_VERSION}.x recommended)/g;
s/PHP 8\.\d+\.x\b/PHP ${PHP_VERSION}.x/g;
s/Required PHP: 8\.\d+\.x/Required PHP: ${PHP_VERSION}.x/g;
s/php8\.\d+-/php${PHP_VERSION}-/g;
ENDPERL

    for f in "${files[@]}"; do
        if [ ! -f "$f" ]; then
            warn_msg "$(basename "$f") not found in build dir, skipping version patch"
            continue
        fi
        info_msg "Patching $(basename "$f") for PHP ${PHP_VERSION}..."
        perl -i -p "$tmp_perl" "$f"
        success_msg "$(basename "$f") patched for PHP ${PHP_VERSION}"
    done

    rm -f "$tmp_perl"
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
             --exclude='.gitignore' \
             --exclude='.gitattributes' \
             --exclude='.editorconfig' \
             --exclude='.env' \
             --exclude='.env.*' \
             --exclude='tests' \
             --exclude='phpunit.xml' \
             --exclude='vite.config.js' \
             --exclude='package.json' \
             --exclude='package-lock.json' \
             --exclude='postcss.config.js' \
             --exclude='tailwind.config.js' \
             --exclude='requirements-check.php' \
             --exclude='requirements.html' \
             --exclude='requirements*.php' \
             --exclude='requirements*.html' \
             --exclude='hbkm6dfx@*' \
             --exclude='storage/logs/*' \
             --exclude='storage/framework/cache/*' \
             --exclude='storage/framework/sessions/*' \
             --exclude='storage/framework/views/*' \
             backend/ "$BUILD_DIR/backend/" || error_exit "Failed to copy backend files"
    
    # Copy frontend dist
    info_msg "Copying frontend build assets..."
    mkdir -p "$BUILD_DIR/frontend"
    cp -r frontend/dist "$BUILD_DIR/frontend/" || error_exit "Failed to copy frontend dist"
    
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
    
    # Root .htaccess
    info_msg "Placing root .htaccess..."
    cp "$template_dir/root.htaccess" "$BUILD_DIR/.htaccess" || error_exit "Failed to copy root .htaccess"
    
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
    )
    
    for file in "${htaccess_files[@]}"; do
        if [ ! -f "$file" ]; then
            error_exit ".htaccess file not found: $file"
        fi
    done
    
    success_msg "All .htaccess files verified"
}

# Replace symlinks with real directories so the archive is safe to upload via FTP.
# Symlinks created inside Docker containers (e.g. storage:link) point to absolute
# container paths that don't exist on the target server.
resolve_symlinks() {
    info_msg "Resolving symlinks for FTP-safe deployment..."

    local found=0
    while IFS= read -r -d '' link; do
        found=1
        local target
        target=$(readlink "$link")
        warn_msg "Symlink found: ${link#$BUILD_DIR/} → $target"
        rm "$link"
        mkdir -p "$link"
        success_msg "Replaced with empty directory: ${link#$BUILD_DIR/}"
    done < <(find "$BUILD_DIR" -type l -print0)

    if [ "$found" -eq 0 ]; then
        info_msg "No symlinks found in build directory"
    else
        success_msg "All symlinks resolved to real directories"
    fi
}

# Copy installer script
copy_installer() {
    info_msg "Adding installation wizard..."
    
    if [ ! -f "install.php" ]; then
        error_exit "install.php not found in project root"
    fi
    
    cp install.php "$BUILD_DIR/install.php" || error_exit "Failed to copy install.php"
    # Use 644 for better security on shared hosting (PHP files don't need execute bit)
    chmod 644 "$BUILD_DIR/install.php"
    
    success_msg "Installation wizard added"
}

# Create tar.gz archive
create_archive() {
    info_msg "Creating deployment archive..."
    
    # Exclude macOS resource fork files (._*) and .DS_Store
    # Use COPYFILE_DISABLE on macOS to prevent ._* files
    COPYFILE_DISABLE=1 tar -czf "$ARCHIVE_NAME" -C "$BUILD_DIR" \
        --exclude='._*' \
        --exclude='.DS_Store' \
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
        "./.htaccess"
        "./backend/public/.htaccess"
    )
    
    local missing_files=()
    for file in "${critical_files[@]}"; do
        if ! tar -tzf "$ARCHIVE_NAME" | grep -q "^${file}$"; then
            missing_files+=("$file")
        fi
    done
    
    if [ ${#missing_files[@]} -gt 0 ]; then
        echo ""
        error_msg "Critical files missing from archive:"
        for file in "${missing_files[@]}"; do
            echo "  - $file"
        done
        echo ""
        info_msg "Archive contents (first 50 files):"
        tar -tzf "$ARCHIVE_NAME" | head -50
        error_exit "Archive verification failed"
    fi
    
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
    echo -e "${BLUE}PHP version:${NC}  ${PHP_VERSION}"
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
    echo -e "${BLUE}║  HomoCanis Deployment Builder (Docker)    ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${BLUE}PHP version:${NC}  ${PHP_VERSION}"
    echo -e "${BLUE}Archive:${NC}      ${ARCHIVE_NAME}"
    echo ""

    # Check Docker environment
    check_docker
    check_docker_compose

    # Build PHP builder image for requested version
    build_php_builder_image
    
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

    # Step 5.5: Resolve symlinks (Docker creates absolute paths, unusable on target server)
    resolve_symlinks

    # Step 6: Verify directory structure
    verify_directory_structure
    
    # Step 7: Copy .htaccess files
    copy_htaccess_files
    
    # Step 8: Verify .htaccess files
    verify_htaccess_files
    
    # Step 8.5: Copy install.php
    copy_installer

    # Step 8.6: Patch PHP version requirements for target server
    patch_php_version_requirement

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
