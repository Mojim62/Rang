#!/bin/bash
# Bamero One-Command Deployment Script
# Usage: ./scripts/deploy.sh production|staging
# Description: Automates the entire deployment process for Bamero store

set -e

ENV=${1:-"staging"}
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

# Validate environment
validate_environment() {
    log_info "Validating environment..."
    
    if [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_NAME" ]; then
        log_error "Database credentials not set. Please set DB_USER, DB_PASS, DB_NAME."
        exit 1
    fi
    
    if [ "$ENV" = "production" ]; then
        if [ -z "$ZARINPAL_MERCHANT_ID" ] || [ -z "$SMS_IR_API_KEY" ]; then
            log_error "Production secrets not set. Please set ZARINPAL_MERCHANT_ID and SMS_IR_API_KEY."
            exit 1
        fi
    fi
    
    log_success "Environment validated"
}

# Create backups
create_backups() {
    log_info "Creating backups..."
    
    TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_DIR="$PROJECT_ROOT/backups"
    
    mkdir -p "$BACKUP_DIR"
    
    # Database backup
    if command -v mysqldump &> /dev/null; then
        mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_DIR/bamero_db_${TIMESTAMP}.sql" 2>/dev/null
        log_success "Database backup created: bamero_db_${TIMESTAMP}.sql"
    else
        log_warning "mysqldump not found. Skipping database backup."
    fi
    
    # Files backup
    tar -czvf "$BACKUP_DIR/bamero_files_${TIMESTAMP}.tar.gz" -C "$PROJECT_ROOT" . 2>/dev/null || true
    log_success "Files backup created: bamero_files_${TIMESTAMP}.tar.gz"
    
    # Keep only last 5 backups
    ls -t "$BACKUP_DIR"/bamero_* 2>/dev/null | tail -n +6 | xargs rm -f 2>/dev/null || true
}

# Pull latest code
pull_code() {
    log_info "Pulling latest code..."
    
    cd "$PROJECT_ROOT"
    git fetch origin
    git checkout main
    git pull origin main
    
    log_success "Code updated to latest version"
}

# Install dependencies
install_dependencies() {
    log_info "Installing dependencies..."
    
    cd "$PROJECT_ROOT"
    
    # Check for composer
    if [ -f "composer.json" ] && command -v composer &> /dev/null; then
        composer install --no-dev --optimize-autoloader 2>/dev/null || true
        log_success "Composer dependencies installed"
    else
        log_info "No composer.json found or composer not installed. Skipping."
    fi
}

# Set environment variables
set_environment() {
    log_info "Setting environment for $ENV..."
    
    # Create .env file if it doesn't exist
    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        cp "$PROJECT_ROOT/.env.example" "$PROJECT_ROOT/.env" 2>/dev/null || true
    fi
    
    # Set environment-specific variables
    if [ "$ENV" = "production" ]; then
        # Production settings
        sed -i "s/BAMERO_ENVIRONMENT=.*/BAMERO_ENVIRONMENT=production/" "$PROJECT_ROOT/.env" 2>/dev/null || true
        sed -i "s/WP_DEBUG=.*/WP_DEBUG=0/" "$PROJECT_ROOT/.env" 2>/dev/null || true
        sed -i "s/DISALLOW_FILE_MODS=.*/DISALLOW_FILE_MODS=1/" "$PROJECT_ROOT/.env" 2>/dev/null || true
        log_success "Production environment configured"
    else
        # Staging settings
        sed -i "s/BAMERO_ENVIRONMENT=.*/BAMERO_ENVIRONMENT=staging/" "$PROJECT_ROOT/.env" 2>/dev/null || true
        sed -i "s/WP_DEBUG=.*/WP_DEBUG=1/" "$PROJECT_ROOT/.env" 2>/dev/null || true
        sed -i "s/DISALLOW_FILE_MODS=.*/DISALLOW_FILE_MODS=0/" "$PROJECT_ROOT/.env" 2>/dev/null || true
        log_success "Staging environment configured"
    fi
}

# Setup WordPress
setup_wordpress() {
    log_info "Setting up WordPress..."
    
    cd "$PROJECT_ROOT"
    
    # Check if WordPress is installed
    if ! wp core is-installed 2>/dev/null; then
        log_info "Installing WordPress..."
        wp core install \
            --url="https://${DOMAIN:-your-domain.com}" \
            --title="بامرو" \
            --admin_user="${WP_ADMIN_USER:-admin}" \
            --admin_password="${WP_ADMIN_PASS:-admin}" \
            --admin_email="${WP_ADMIN_EMAIL:-admin@your-domain.com}" \
            --locale="fa_IR" \
            --skip-email
        
        log_success "WordPress installed"
    else
        log_info "WordPress is already installed"
    fi
    
    # Install WooCommerce
    if ! wp plugin is-active woocommerce 2>/dev/null; then
        log_info "Installing WooCommerce..."
        wp plugin install woocommerce --activate
        wp plugin activate woocommerce
        log_success "WooCommerce installed and activated"
    else
        log_info "WooCommerce is already active"
    fi
}

# Activate Bamero plugins
activate_bamero_plugins() {
    log_info "Activating Bamero plugins..."
    
    cd "$PROJECT_ROOT"
    
    BAMERO_PLUGINS=(
        "bamero-zarinpal-gateway/bamero-zarinpal-gateway.php"
        "bamero-mobile-auth/bamero-mobile-auth.php"
        "bamero-production-core/bamero-production-core.php"
        "bamero-woocommerce-setup/bamero-woocommerce-setup.php"
        "bamero-essential-plugins/bamero-essential-plugins.php"
        "bamero-custom-plugin/bamero-custom-plugin.php"
    )
    
    for plugin in "${BAMERO_PLUGINS[@]}"; do
        if wp plugin is-active "$plugin" 2>/dev/null; then
            log_info "Plugin $plugin is already active"
        else
            wp plugin activate "$plugin" 2>/dev/null || log_warning "Failed to activate $plugin"
            log_success "Activated: $plugin"
        fi
    done
}

# Configure WooCommerce
configure_woocommerce() {
    log_info "Configuring WooCommerce..."
    
    cd "$PROJECT_ROOT"
    
    # Basic settings
    wp option update woocommerce_currency IRR
    wp option update woocommerce_currency_pos suffix
    wp option update woocommerce_weight_unit kg
    wp option update woocommerce_dimension_unit cm
    wp option update woocommerce_default_country IR
    wp option update woocommerce_enable_coupons no
    wp option update woocommerce_enable_myaccount_registration no
    wp option update woocommerce_enable_guest_checkout yes
    wp option update woocommerce_ship_to_countries "IR"
    wp option update woocommerce_allowed_countries "IR"
    
    # Create required pages
    create_required_pages
    
    log_success "WooCommerce configured"
}

# Create required pages
create_required_pages() {
    log_info "Creating required pages..."
    
    cd "$PROJECT_ROOT"
    
    # Standard WooCommerce pages
    SHOP_PAGE=$(wp post create --post_type=page --post_title="فروشگاه" --post_status=publish --post_name="shop" --porcelain 2>/dev/null)
    CART_PAGE=$(wp post create --post_type=page --post_title="سبد خرید" --post_status=publish --post_name="cart" --porcelain 2>/dev/null)
    CHECKOUT_PAGE=$(wp post create --post_type=page --post_title="تکمیل سفارش" --post_status=publish --post_name="checkout" --porcelain 2>/dev/null)
    MYACCOUNT_PAGE=$(wp post create --post_type=page --post_title="حساب کاربری" --post_status=publish --post_name="my-account" --porcelain 2>/dev/null)
    
    # Custom Bamero pages
    CONSULTATION_PAGE=$(wp post create --post_type=page --post_title="مشاوره رنگ" --post_status=publish --post_name="consultation" --porcelain 2>/dev/null)
    ABOUT_PAGE=$(wp post create --post_type=page --post_title="درباره ما" --post_status=publish --post_name="about" --porcelain 2>/dev/null)
    CONTACT_PAGE=$(wp post create --post_type=page --post_title="تماس با ما" --post_status=publish --post_name="contact" --porcelain 2>/dev/null)
    PRIVACY_PAGE=$(wp post create --post_type=page --post_title="حریم خصوصی" --post_status=publish --post_name="privacy-policy" --porcelain 2>/dev/null)
    TERMS_PAGE=$(wp post create --post_type=page --post_title="شرایط استفاده" --post_status=publish --post_name="terms-of-service" --porcelain 2>/dev/null)
    
    # Set WooCommerce pages
    if [ -n "$SHOP_PAGE" ]; then
        wp option update woocommerce_shop_page_id "$SHOP_PAGE"
    fi
    if [ -n "$CART_PAGE" ]; then
        wp option update woocommerce_cart_page_id "$CART_PAGE"
    fi
    if [ -n "$CHECKOUT_PAGE" ]; then
        wp option update woocommerce_checkout_page_id "$CHECKOUT_PAGE"
    fi
    if [ -n "$MYACCOUNT_PAGE" ]; then
        wp option update woocommerce_myaccount_page_id "$MYACCOUNT_PAGE"
    fi
    
    log_success "Required pages created"
}

# Run migrations
run_migrations() {
    log_info "Running migrations..."
    
    cd "$PROJECT_ROOT"
    
    # Run WooCommerce setup
    if [ -f "$PROJECT_ROOT/wp-content/plugins/bamero-woocommerce-setup/bamero-woocommerce-setup.php" ]; then
        wp plugin activate bamero-woocommerce-setup 2>/dev/null || true
        log_success "Bamero WooCommerce setup executed"
    fi
    
    # Flush rewrite rules
    wp rewrite flush
    log_success "Rewrite rules flushed"
}

# Clear caches
clear_caches() {
    log_info "Clearing caches..."
    
    cd "$PROJECT_ROOT"
    
    wp cache flush
    wp transient delete-all
    
    log_success "Caches cleared"
}

# Set permissions
set_permissions() {
    log_info "Setting permissions..."
    
    cd "$PROJECT_ROOT"
    
    if [ "$EUID" -ne 0 ]; then
        # Running as non-root, use sudo if available
        sudo chown -R www-data:www-data . 2>/dev/null || chown -R $(whoami):$(whoami) .
        sudo find . -type d -exec chmod 755 {} \; 2>/dev/null || find . -type d -exec chmod 755 {} \;
        sudo find . -type f -exec chmod 644 {} \; 2>/dev/null || find . -type f -exec chmod 644 {} \;
    else
        chown -R www-data:www-data . 2>/dev/null || true
        find . -type d -exec chmod 755 {} \; 2>/dev/null || true
        find . -type f -exec chmod 644 {} \; 2>/dev/null || true
    fi
    
    log_success "Permissions set"
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
}

# Main deployment function
main() {
    log_info "Starting Bamero deployment for $ENV environment..."
    
    validate_environment
    create_backups
    pull_code
    install_dependencies
    set_environment
    setup_wordpress
    activate_bamero_plugins
    configure_woocommerce
    run_migrations
    clear_caches
    set_permissions
    restart_services
    
    log_success ""
    log_success "=========================================="
    log_success "✅ Deployment complete for $ENV environment!"
    log_success "=========================================="
    log_info ""
    log_info "Next steps:"
    log_info "1. Set production secrets in environment"
    log_info "2. Run validation: ./scripts/validate.sh"
    log_info "3. Test all scenarios"
    log_info "4. Go-Live! 🚀"
}

# Run main
main "$@"
