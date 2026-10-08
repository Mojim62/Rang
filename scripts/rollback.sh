#!/bin/bash
# Bamero Rollback Script
# Usage: ./scripts/rollback.sh [commit_hash]
# Description: Automates rollback to a previous commit

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Default commit (last stable)
DEFAULT_COMMIT="acf9db4"

# Parse arguments
COMMIT=${1:-$DEFAULT_COMMIT}
CONFIRM=${2:-false}

# Confirmation function
confirm_rollback() {
    if [ "$CONFIRM" = "--force" ] || [ "$CONFIRM" = "-f" ]; then
        return 0
    fi
    
    echo ""
    log_warning "⚠️  This will rollback to commit: $COMMIT"
    log_warning "⚠️  All changes after this commit will be lost!"
    echo ""
    read -p "Are you sure you want to rollback? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_error "Rollback cancelled by user"
        exit 1
    fi
    return 0
}

# Create pre-rollback backup
create_prerollback_backup() {
    log_info "Creating pre-rollback backup..."
    
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_DIR="$PROJECT_ROOT/backups"
    
    mkdir -p "$BACKUP_DIR"
    
    # Database backup
    if command -v mysqldump &> /dev/null; then
        DB_BACKUP="$BACKUP_DIR/bamero_db_prerollback_${TIMESTAMP}.sql"
        mysqldump -u "${DB_USER:-root}" -p"${DB_PASS:-}" "${DB_NAME:-bamero_db}" > "$DB_BACKUP" 2>/dev/null
        if [ -f "$DB_BACKUP" ]; then
            log_success "Database backup created: $(basename "$DB_BACKUP")"
        else
            log_error "Failed to create database backup"
            return 1
        fi
    else
        log_warning "mysqldump not found. Skipping database backup."
    fi
    
    # Files backup
    FILES_BACKUP="$BACKUP_DIR/bamero_files_prerollback_${TIMESTAMP}.tar.gz"
    tar -czvf "$FILES_BACKUP" -C "$PROJECT_ROOT" . 2>/dev/null || true
    if [ -f "$FILES_BACKUP" ]; then
        log_success "Files backup created: $(basename "$FILES_BACKUP")"
    else
        log_error "Failed to create files backup"
        return 1
    fi
    
    echo "$BACKUP_DIR" > "$PROJECT_ROOT/last_rollback_backup.txt"
    return 0
}

# Checkout to previous commit
checkout_commit() {
    log_info "Checking out to commit: $COMMIT..."
    
    cd "$PROJECT_ROOT"
    
    # Stash any uncommitted changes
    if git status --porcelain | grep -q .; then
        git stash push -m "Pre-rollback stash" 2>/dev/null || true
        log_info "Uncommitted changes stashed"
    fi
    
    # Checkout to target commit
    if git checkout "$COMMIT" --force 2>/dev/null; then
        log_success "Checked out to commit: $COMMIT"
    else
        log_error "Failed to checkout to commit: $COMMIT"
        return 1
    fi
    
    return 0
}

# Restore database from backup
restore_database() {
    log_info "Restoring database from backup..."
    
    cd "$PROJECT_ROOT"
    
    # Find the most recent database backup
    BACKUP_DIR="$PROJECT_ROOT/backups"
    DB_BACKUP=$(ls -t "$BACKUP_DIR"/bamero_db_*.sql 2>/dev/null | head -1)
    
    if [ -z "$DB_BACKUP" ]; then
        log_warning "No database backup found. Skipping database restore."
        return 0
    fi
    
    # Extract timestamp from backup filename
    BACKUP_TIMESTAMP=$(basename "$DB_BACKUP" | grep -oE '[0-9]{8}_[0-9]{6}')
    
    log_info "Restoring database from: $(basename "$DB_BACKUP")"
    
    # Check if this is a pre-rollback backup
    if [[ "$DB_BACKUP" == *"prerollback"* ]]; then
        log_warning "Restoring from pre-rollback backup. This will restore the state before the failed deployment."
    fi
    
    # Import database
    if command -v mysql &> /dev/null; then
        if mysql -u "${DB_USER:-root}" -p"${DB_PASS:-}" "${DB_NAME:-bamero_db}" < "$DB_BACKUP" 2>/dev/null; then
            log_success "Database restored from backup"
        else
            log_error "Failed to restore database"
            return 1
        fi
    else
        log_error "mysql command not found. Cannot restore database."
        return 1
    fi
    
    return 0
}

# Flush caches
flush_caches() {
    log_info "Flushing caches..."
    
    cd "$PROJECT_ROOT"
    
    if command -v wp &> /dev/null; then
        wp cache flush 2>/dev/null || true
        wp transient delete-all 2>/dev/null || true
        wp rewrite flush 2>/dev/null || true
        log_success "Caches flushed"
    else
        log_warning "WP-CLI not found. Caches not flushed."
    fi
    
    return 0
}

# Set permissions
set_permissions() {
    log_info "Setting permissions..."
    
    cd "$PROJECT_ROOT"
    
    if [ "$EUID" -ne 0 ]; then
        sudo chown -R www-data:www-data . 2>/dev/null || chown -R $(whoami):$(whoami) .
        sudo find . -type d -exec chmod 755 {} \; 2>/dev/null || find . -type d -exec chmod 755 {} \;
        sudo find . -type f -exec chmod 644 {} \; 2>/dev/null || find . -type f -exec chmod 644 {} \;
    else
        chown -R www-data:www-data . 2>/dev/null || true
        find . -type d -exec chmod 755 {} \; 2>/dev/null || true
        find . -type f -exec chmod 644 {} \; 2>/dev/null || true
    fi
    
    # Secure sensitive files
    if [ -f "wp-config.php" ]; then
        chmod 440 wp-config.php 2>/dev/null || chmod 400 wp-config.php 2>/dev/null || true
    fi
    
    if [ -f ".env" ]; then
        chmod 400 .env 2>/dev/null || true
    fi
    
    log_success "Permissions set"
    return 0
}

# Restart services
restart_services() {
    log_info "Restarting services..."
    
    if command -v systemctl &> /dev/null; then
        sudo systemctl restart php8.3-fpm 2>/dev/null || sudo systemctl restart php-fpm 2>/dev/null || true
        sudo systemctl restart nginx 2>/dev/null || sudo systemctl restart apache2 2>/dev/null || true
        log_success "Services restarted"
    else
        log_warning "systemctl not found. Services not restarted."
    fi
    
    return 0
}

# Main rollback function
main() {
    echo ""
    echo "=========================================="
    echo "   Bamero Rollback Script"
    echo "=========================================="
    echo ""
    
    # Show current state
    cd "$PROJECT_ROOT"
    CURRENT_COMMIT=$(git rev-parse --short HEAD 2>/dev/null)
    log_info "Current commit: $CURRENT_COMMIT"
    log_info "Target commit: $COMMIT"
    echo ""
    
    # Confirm rollback
    confirm_rollback
    
    # Create backup
    if ! create_prerollback_backup; then
        log_error "Failed to create pre-rollback backup. Aborting."
        exit 1
    fi
    
    # Checkout to commit
    if ! checkout_commit; then
        log_error "Failed to checkout to commit. Aborting."
        exit 1
    fi
    
    # Restore database
    if ! restore_database; then
        log_error "Failed to restore database. Aborting."
        exit 1
    fi
    
    # Flush caches
    flush_caches
    
    # Set permissions
    set_permissions
    
    # Restart services
    restart_services
    
    echo ""
    echo "=========================================="
    echo "   Rollback Summary"
    echo "=========================================="
    echo ""
    log_success "✅ Rollback to $COMMIT completed successfully!"
    echo ""
    log_info "Backup location: $(cat "$PROJECT_ROOT/last_rollback_backup.txt" 2>/dev/null)"
    echo ""
    log_info "Next steps:"
    log_info "1. Verify the site is working correctly"
    log_info "2. Run validation: ./scripts/validate.sh"
    log_info "3. Investigate what caused the issue"
    log_info ""
}

# Run main
main "$@"
