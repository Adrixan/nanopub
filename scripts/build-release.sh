#!/usr/bin/env bash
# Description: Build a release archive for nanopub deployment
# Usage: ./scripts/build-release.sh [options]
# Requirements: composer, tar, (zip optional)
#
# Options:
#   --help              Show this help message
#   --output-dir DIR    Specify output directory (default: project root)
#   --zip               Also create a .zip archive
#   --no-composer       Skip composer install (for testing)
#
# Examples:
#   ./scripts/build-release.sh
#   ./scripts/build-release.sh --zip --output-dir ~/releases
#   ./scripts/build-release.sh --no-composer  # For testing script logic

set -euo pipefail
IFS=$'\n\t'

[[ "${DEBUG:-0}" == "1" ]] && set -x

# =============================================================================
# Configuration
# =============================================================================

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE_DIR="${PROJECT_ROOT}/release"
VERSION=""
DATE=$(date +%Y%m%d)
INCLUDE_ZIP=false
OUTPUT_DIR="${PROJECT_ROOT}"
NO_COMPOSER=false

# Files and directories to include
INCLUDE_DIRS=(
    "public"
    "src"
    "config"
    "cron"
    "database"
    "storage"
)

INCLUDE_FILES=(
    "composer.json"
    "composer.lock"
    "LICENSE"
    ".htaccess"
)

# Patterns to exclude from the build
EXCLUDE_PATTERNS=(
    ".git"
    ".gitmodules"
    ".kilocode"
    "tests"
    "*.md"
    ".idea"
    ".vscode"
    "*.swp"
    "*.swo"
    "*~"
    ".DS_Store"
    "Thumbs.db"
    "phpunit.xml"
    "phpunit.xml.dist"
    ".phpcs.xml"
    ".phpcs.xml.dist"
    "phpstan.neon"
    "phpstan.neon.dist"
    "docs"
)

# =============================================================================
# Functions
# =============================================================================

usage() {
    cat << EOF
Usage: $(basename "$0") [options]

Build a production-ready release archive for NanoPub.

Options:
  --help              Show this help message
  --output-dir DIR    Specify output directory (default: project root)
  --zip               Also create a .zip archive
  --no-composer       Skip composer install (for testing)

Archive naming: nanopub-{version}-{date}.tar.gz

Version detection:
  1. Git tag (exact match)
  2. Git describe (for development builds)
  3. Fallback to "dev"

Examples:
  $(basename "$0")
  $(basename "$0") --zip
  $(basename "$0") --output-dir ~/releases --zip
  $(basename "$0") --no-composer  # For testing

EOF
}

log_info() {
    echo "[INFO] $1"
}

log_error() {
    echo "[ERROR] $1" >&2
}

detect_version() {
    if git -C "$PROJECT_ROOT" describe --tags --exact-match &>/dev/null; then
        VERSION=$(git -C "$PROJECT_ROOT" describe --tags --exact-match)
        log_info "Detected version from git tag: $VERSION"
    elif git -C "$PROJECT_ROOT" describe --tags &>/dev/null; then
        VERSION=$(git -C "$PROJECT_ROOT" describe --tags)
        log_info "Detected version from git describe: $VERSION"
    else
        VERSION="dev"
        log_info "No git version detected, using: $VERSION"
    fi
}

check_dependencies() {
    local missing=()
    
    if ! command -v tar &>/dev/null; then
        missing+=("tar")
    fi
    
    if [[ "$NO_COMPOSER" == false ]] && ! command -v composer &>/dev/null; then
        missing+=("composer")
    fi
    
    if [[ "$INCLUDE_ZIP" == true ]] && ! command -v zip &>/dev/null; then
        missing+=("zip")
    fi
    
    if [[ ${#missing[@]} -gt 0 ]]; then
        log_error "Missing required dependencies: ${missing[*]}"
        log_error "Please install them and try again."
        exit 1
    fi
}

clean_previous_build() {
    log_info "Cleaning previous build artifacts..."
    
    # Remove release directory
    if [[ -d "$RELEASE_DIR" ]]; then
        rm -rf "$RELEASE_DIR"
    fi
    
    # Remove old archives with same version/date
    local archive_name="nanopub-${VERSION}-${DATE}"
    rm -f "${OUTPUT_DIR}/${archive_name}.tar.gz"
    rm -f "${OUTPUT_DIR}/${archive_name}.zip"
}

create_build_directory() {
    local archive_name="$1"
    local build_dir="${RELEASE_DIR}/${archive_name}"
    
    log_info "Creating build directory: $build_dir" >&2
    mkdir -p "$build_dir"
    
    echo "$build_dir"
}

copy_production_files() {
    local build_dir="$1"
    
    log_info "Copying production files..."
    
    # Copy directories
    for dir in "${INCLUDE_DIRS[@]}"; do
        local src_path="${PROJECT_ROOT}/${dir}"
        if [[ -d "$src_path" ]]; then
            log_info "  Copying directory: $dir"
            cp -r "$src_path" "${build_dir}/${dir}"
        else
            log_error "  Directory not found: $dir"
            exit 1
        fi
    done
    
    # Copy individual files
    for file in "${INCLUDE_FILES[@]}"; do
        local src_path="${PROJECT_ROOT}/${file}"
        if [[ -f "$src_path" ]]; then
            log_info "  Copying file: $file"
            cp "$src_path" "${build_dir}/${file}"
        else
            log_error "  File not found: $file"
            exit 1
        fi
    done
}

prepare_open_basedir_files() {
    local build_dir="$1"
    
    log_info "Preparing files for open_basedir restricted hosting..."
    
    # Copy src to public/storage/src/
    mkdir -p "${build_dir}/public/storage/src"
    cp -r "${build_dir}/src/"* "${build_dir}/public/storage/src/"
    
    # Copy Views to public/storage/Views/
    mkdir -p "${build_dir}/public/storage/Views"
    if [[ -d "${build_dir}/src/Views" ]]; then
        cp -r "${build_dir}/src/Views/"* "${build_dir}/public/storage/Views/"
    fi
    
    # Copy config to public/storage/config/
    mkdir -p "${build_dir}/public/storage/config"
    cp -r "${build_dir}/config/"* "${build_dir}/public/storage/config/"
    
    # Copy database to public/storage/database/
    mkdir -p "${build_dir}/public/storage/database"
    cp -r "${build_dir}/database/"* "${build_dir}/public/storage/database/"
    
    log_info "Open_basedir files prepared"
}

remove_excluded_files() {
    local build_dir="$1"
    
    log_info "Removing excluded files from build..."
    
    # Remove .md files (except LICENSE-related if any)
    find "$build_dir" -name "*.md" -type f -delete
    
    # Remove .git directory if it was copied
    rm -rf "${build_dir}/.git"
    rm -f "${build_dir}/.gitmodules"
    
    # Remove .kilocode directory
    rm -rf "${build_dir}/.kilocode"
    
    # Remove tests directory
    rm -rf "${build_dir}/tests"
    
    # Remove IDE files
    rm -rf "${build_dir}/.idea"
    rm -rf "${build_dir}/.vscode"
    
    # Remove vim swap files
    find "$build_dir" -name "*.swp" -type f -delete
    find "$build_dir" -name "*.swo" -type f -delete
    find "$build_dir" -name "*~" -type f -delete
    
    # Remove OS files
    find "$build_dir" -name ".DS_Store" -type f -delete
    find "$build_dir" -name "Thumbs.db" -type f -delete
    
    # Remove development config files
    rm -f "${build_dir}/phpunit.xml"
    rm -f "${build_dir}/phpunit.xml.dist"
    rm -f "${build_dir}/.phpcs.xml"
    rm -f "${build_dir}/.phpcs.xml.dist"
    rm -f "${build_dir}/phpstan.neon"
    rm -f "${build_dir}/phpstan.neon.dist"
    
    # Remove docs directory
    rm -rf "${build_dir}/docs"
}

run_composer_install() {
    local build_dir="$1"
    
    if [[ "$NO_COMPOSER" == true ]]; then
        log_info "Skipping composer install (--no-composer flag)"
        return
    fi
    
    log_info "Running composer install (production mode)..."
    
    composer install \
        --no-dev \
        --no-interaction \
        --optimize-autoloader \
        --no-progress \
        --working-dir="$build_dir"
    
    log_info "Composer install completed"
}

set_permissions() {
    local build_dir="$1"
    
    log_info "Setting file permissions..."
    
    # Set directory permissions to 755
    find "$build_dir" -type d -exec chmod 755 {} \;
    
    # Set file permissions to 644
    find "$build_dir" -type f -exec chmod 644 {} \;
    
    log_info "Permissions set: directories 755, files 644"
}

create_archives() {
    local archive_name="$1"
    local build_dir="${RELEASE_DIR}/${archive_name}"
    
    log_info "Creating release archives..."
    
    # Ensure output directory exists
    mkdir -p "$OUTPUT_DIR"
    
    # Create tar.gz archive
    local tar_path="${OUTPUT_DIR}/${archive_name}.tar.gz"
    log_info "Creating tar.gz archive: $tar_path"
    tar -czf "$tar_path" -C "${RELEASE_DIR}" "$archive_name"
    
    # Create zip archive if requested
    if [[ "$INCLUDE_ZIP" == true ]]; then
        local zip_path="${OUTPUT_DIR}/${archive_name}.zip"
        log_info "Creating zip archive: $zip_path"
        (cd "${RELEASE_DIR}" && zip -rq "${zip_path}" "$archive_name")
    fi
}

cleanup() {
    log_info "Cleaning up temporary files..."
    
    if [[ -d "$RELEASE_DIR" ]]; then
        rm -rf "$RELEASE_DIR"
    fi
}

print_summary() {
    local archive_name="$1"
    
    echo ""
    echo "=========================================="
    echo "Release Build Complete"
    echo "=========================================="
    echo "Version:  $VERSION"
    echo "Date:     $DATE"
    echo ""
    echo "Archives created:"
    echo "  - ${OUTPUT_DIR}/${archive_name}.tar.gz"
    
    if [[ "$INCLUDE_ZIP" == true ]]; then
        echo "  - ${OUTPUT_DIR}/${archive_name}.zip"
    fi
    
    echo ""
    echo "To deploy:"
    echo "  1. Extract: tar -xzf ${archive_name}.tar.gz"
    echo "  2. Set document root to: ${archive_name}/public/"
    echo "  3. Run installer or configure manually"
    echo ""
    echo "NOTE: For open_basedir restricted hosting:"
    echo "  - Config files are already in public/storage/config/"
    echo "  - No additional setup needed for code access"
    echo "=========================================="
}

# =============================================================================
# Main
# =============================================================================

main() {
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --help)
                usage
                exit 0
                ;;
            --output-dir)
                if [[ -z "${2:-}" ]]; then
                    log_error "--output-dir requires a directory path"
                    exit 1
                fi
                OUTPUT_DIR="$2"
                shift 2
                ;;
            --output-dir=*)
                OUTPUT_DIR="${1#*=}"
                shift
                ;;
            --zip)
                INCLUDE_ZIP=true
                shift
                ;;
            --no-composer)
                NO_COMPOSER=true
                shift
                ;;
            *)
                log_error "Unknown option: $1"
                usage
                exit 1
                ;;
        esac
    done
    
    # Validate output directory
    if [[ ! -d "$OUTPUT_DIR" ]]; then
        log_error "Output directory does not exist: $OUTPUT_DIR"
        exit 1
    fi
    
    # Check dependencies
    check_dependencies
    
    # Detect version
    detect_version
    
    # Build archive name
    local archive_name="nanopub-${VERSION}-${DATE}"
    
    log_info "Starting release build: $archive_name"
    log_info "Project root: $PROJECT_ROOT"
    log_info "Output directory: $OUTPUT_DIR"
    
    # Clean previous build
    clean_previous_build
    
    # Create build directory
    local build_dir
    build_dir=$(create_build_directory "$archive_name")
    
    # Set up cleanup trap
    trap cleanup EXIT
    
    # Copy production files
    copy_production_files "$build_dir"

    # Prepare files for open_basedir restricted hosting
    prepare_open_basedir_files "$build_dir"

    # Remove excluded files
    remove_excluded_files "$build_dir"
    
    # Run composer install
    run_composer_install "$build_dir"
    
    # Set permissions
    set_permissions "$build_dir"
    
    # Create archives
    create_archives "$archive_name"
    
    # Print summary
    print_summary "$archive_name"
}

# Run main function
main "$@"
