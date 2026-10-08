#!/bin/bash
# Bamero Pre-Go-Live Validation Script
# Usage: ./scripts/validate.sh
# Description: Validates all requirements before Go-Live

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0
PASS_COUNT=0
FAIL_COUNT=0

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    PASS_COUNT=$((PASS_COUNT+1))
}

log_warning() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    WARNINGS=$((WARNINGS+1))
}

log_error() {
    echo -e "${RED}[FAIL]${NC} $1"
    ERRORS=$((ERRORS+1))
    FAIL_COUNT=$((FAIL_COUNT+1))
}

# Check if WP-CLI is available
check_wpcli() {
    if ! command -v wp &> /dev/null; then
        log_error "WP-CLI is not installed. Please install WP-CLI first."
        return 1
    fi
    return 0
}

# 1. Check PHP version
check_php_version() {
    log_info "Checking PHP version..."
    
    PHP_VERSION=$(php -r 'echo PHP_VERSION;' 2>/dev/null)
    if [ -z "$PHP_VERSION" ]; then
        log_error "PHP is not installed or not in PATH"
        return 1
    fi
    
    if [[ $PHP_VERSION =~ ^8\.[0-3] ]]; then
        log_success "PHP version: $PHP_VERSION"
        return 0
    else
        log_error "PHP version must be 8.0-8.3 (Current: $PHP_VERSION)"
        return 1
    fi
}

# 2. Check PHP extensions
check_php_extensions() {
    log_info "Checking PHP extensions..."
    
    REQUIRED_EXTENSIONS=("mbstring" "curl" "openssl" "mysql" "mysqli" "pdo_mysql" "json" "xml" "gd" "zip")
    MISSING_EXTENSIONS=()
    
    for ext in "${REQUIRED_EXTENSIONS[@]}"; do
        if ! php -m 2>/dev/null | grep -q "$ext"; then
            MISSING_EXTENSIONS+=("$ext")
        fi
    done
    
    if [ ${#MISSING_EXTENSIONS[@]} -eq 0 ]; then
        log_success "All required PHP extensions are installed"
        return 0
    else
        log_error "Missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
        return 1
    fi
}

# 3. Check WordPress installation
check_wordpress() {
    log_info "Checking WordPress installation..."
    
    cd "$PROJECT_ROOT"
    
    if ! wp core is-installed 2>/dev/null; then
        log_error "WordPress is not installed"
        return 1
    fi
    
    WP_VERSION=$(wp core version 2>/dev/null)
    if [[ $WP_VERSION =~ ^6\. ]]; then
        log_success "WordPress is installed (Version: $WP_VERSION)"
        return 0
    else
        log_error "WordPress version must be 6.x (Current: $WP_VERSION)"
        return 1
    fi
}

# 4. Check WooCommerce
check_woocommerce() {
    log_info "Checking WooCommerce..."
    
    cd "$PROJECT_ROOT"
    
    if ! wp plugin is-active woocommerce 2>/dev/null; then
        log_error "WooCommerce is not active"
        return 1
    fi
    
    WC_VERSION=$(wp plugin get woocommerce --field=version 2>/dev/null)
    log_success "WooCommerce is active (Version: $WC_VERSION)"
    
    # Check settings
    CURRENCY=$(wp option get woocommerce_currency 2>/dev/null)
    if [ "$CURRENCY" != "IRR" ]; then
        log_error "Currency is not IRR (Current: $CURRENCY)"
        return 1
    else
        log_success "Currency: IRR"
    fi
    
    COUPONS=$(wp option get woocommerce_enable_coupons 2>/dev/null)
    if [ "$COUPONS" != "no" ]; then
        log_error "Coupons are enabled (must be disabled)"
        return 1
    else
        log_success "Coupons: Disabled"
    fi
    
    REGISTRATION=$(wp option get woocommerce_enable_myaccount_registration 2>/dev/null)
    if [ "$REGISTRATION" != "no" ]; then
        log_error "MyAccount registration is enabled (must be disabled)"
        return 1
    else
        log_success "MyAccount registration: Disabled"
    fi
    
    return 0
}

# 5. Check Bamero plugins
check_bamero_plugins() {
    log_info "Checking Bamero plugins..."
    
    cd "$PROJECT_ROOT"
    
    BAMERO_PLUGINS=(
        "bamero-zarinpal-gateway/bamero-zarinpal-gateway.php"
        "bamero-mobile-auth/bamero-mobile-auth.php"
        "bamero-production-core/bamero-production-core.php"
        "bamero-woocommerce-setup/bamero-woocommerce-setup.php"
        "bamero-essential-plugins/bamero-essential-plugins.php"
        "bamero-custom-plugin/bamero-custom-plugin.php"
    )
    
    INACTIVE_PLUGINS=()
    
    for plugin in "${BAMERO_PLUGINS[@]}"; do
        if ! wp plugin is-active "$plugin" 2>/dev/null; then
            INACTIVE_PLUGINS+=("$plugin")
        else
            log_success "Plugin active: $plugin"
        fi
    done
    
    if [ ${#INACTIVE_PLUGINS[@]} -gt 0 ]; then
        log_error "Inactive Bamero plugins: ${INACTIVE_PLUGINS[*]}"
        return 1
    fi
    
    return 0
}

# 6. Check required pages
check_required_pages() {
    log_info "Checking required pages..."
    
    cd "$PROJECT_ROOT"
    
    REQUIRED_PAGES=(
        "shop" "cart" "checkout" "my-account"
        "consultation" "about" "contact" "privacy-policy" "terms-of-service"
    )
    
    MISSING_PAGES=()
    
    for slug in "${REQUIRED_PAGES[@]}"; do
        if ! wp post exists --post_name="$slug" --post_type=page 2>/dev/null; then
            MISSING_PAGES+=("$slug")
        else
            log_success "Page exists: $slug"
        fi
    done
    
    if [ ${#MISSING_PAGES[@]} -gt 0 ]; then
        log_error "Missing pages: ${MISSING_PAGES[*]}"
        return 1
    fi
    
    return 0
}

# 7. Check WooCommerce page settings
check_wc_pages() {
    log_info "Checking WooCommerce page settings..."
    
    cd "$PROJECT_ROOT"
    
    SHOP_PAGE=$(wp option get woocommerce_shop_page_id 2>/dev/null)
    CART_PAGE=$(wp option get woocommerce_cart_page_id 2>/dev/null)
    CHECKOUT_PAGE=$(wp option get woocommerce_checkout_page_id 2>/dev/null)
    MYACCOUNT_PAGE=$(wp option get woocommerce_myaccount_page_id 2>/dev/null)
    
    if [ -z "$SHOP_PAGE" ] || [ "$SHOP_PAGE" = "0" ]; then
        log_error "Shop page is not set"
        return 1
    else
        log_success "Shop page: Set"
    fi
    
    if [ -z "$CART_PAGE" ] || [ "$CART_PAGE" = "0" ]; then
        log_error "Cart page is not set"
        return 1
    else
        log_success "Cart page: Set"
    fi
    
    if [ -z "$CHECKOUT_PAGE" ] || [ "$CHECKOUT_PAGE" = "0" ]; then
        log_error "Checkout page is not set"
        return 1
    else
        log_success "Checkout page: Set"
    fi
    
    if [ -z "$MYACCOUNT_PAGE" ] || [ "$MYACCOUNT_PAGE" = "0" ]; then
        log_error "MyAccount page is not set"
        return 1
    else
        log_success "MyAccount page: Set"
    fi
    
    return 0
}

# 8. Check database tables
check_database() {
    log_info "Checking database tables..."
    
    cd "$PROJECT_ROOT"
    
    # Check if HPOS tables exist
    HPOS_TABLES=$(wp db query "SHOW TABLES LIKE '%%wc_orders%%'" 2>/dev/null)
    if [ -n "$HPOS_TABLES" ]; then
        log_success "HPOS tables exist (WooCommerce using custom order tables)"
    else
        log_warning "HPOS tables not found (may be using legacy tables)"
    fi
    
    # Check Bamero tables
    BAMERO_TABLES=$(wp db query "SHOW TABLES LIKE '%%bamero_notification_outbox%%'" 2>/dev/null)
    if [ -n "$BAMERO_TABLES" ]; then
        log_success "Bamero notification outbox table exists"
    else
        log_warning "Bamero notification outbox table not found"
    fi
    
    return 0
}

# 9. Check environment variables
check_environment() {
    log_info "Checking environment variables..."
    
    ENVIRONMENT=${BAMERO_ENVIRONMENT:-$(wp config get BAMERO_ENVIRONMENT 2>/dev/null)}
    
    if [ "$ENVIRONMENT" = "production" ]; then
        # Production checks
        if [ -z "$ZARINPAL_MERCHANT_ID" ]; then
            log_error "ZARINPAL_MERCHANT_ID is not set (required for production)"
            return 1
        else
            log_success "ZARINPAL_MERCHANT_ID: Set"
        fi
        
        if [ -z "$SMS_IR_API_KEY" ]; then
            log_error "SMS_IR_API_KEY is not set (required for production)"
            return 1
        else
            log_success "SMS_IR_API_KEY: Set"
        fi
        
        # Check production flags
        if [ "$BAMERO_ENABLE_PAYMENT_IN_STAGING" = "true" ]; then
            log_warning "BAMERO_ENABLE_PAYMENT_IN_STAGING is true (should be false in production)"
        else
            log_success "BAMERO_ENABLE_PAYMENT_IN_STAGING: Disabled (correct for production)"
        fi
        
        if [ "$BAMERO_ENABLE_SMS_IN_STAGING" = "true" ]; then
            log_warning "BAMERO_ENABLE_SMS_IN_STAGING is true (should be false in production)"
        else
            log_success "BAMERO_ENABLE_SMS_IN_STAGING: Disabled (correct for production)"
        fi
    else
        # Staging checks
        log_info "Running in staging mode"
        log_success "Environment: $ENVIRONMENT"
    fi
    
    # Check WordPress constants
    WP_DEBUG=$(wp config get WP_DEBUG 2>/dev/null || echo "not_set")
    if [ "$ENVIRONMENT" = "production" ] && [ "$WP_DEBUG" != "false" ]; then
        log_error "WP_DEBUG should be false in production (Current: $WP_DEBUG)"
        return 1
    else
        log_success "WP_DEBUG: $WP_DEBUG"
    fi
    
    DISALLOW_FILE_MODS=$(wp config get DISALLOW_FILE_MODS 2>/dev/null || echo "not_set")
    if [ "$ENVIRONMENT" = "production" ] && [ "$DISALLOW_FILE_MODS" != "true" ]; then
        log_error "DISALLOW_FILE_MODS should be true in production (Current: $DISALLOW_FILE_MODS)"
        return 1
    else
        log_success "DISALLOW_FILE_MODS: $DISALLOW_FILE_MODS"
    fi
    
    return 0
}

# 10. Check cron
check_cron() {
    log_info "Checking cron..."
    
    cd "$PROJECT_ROOT"
    
    if wp cron test 2>/dev/null; then
        log_success "Cron is working"
        return 0
    else
        log_error "Cron is not working"
        return 1
    fi
}

# 11. Check rewrite rules
check_rewrite() {
    log_info "Checking rewrite rules..."
    
    cd "$PROJECT_ROOT"
    
    if wp rewrite list 2>/dev/null | grep -q "bamero"; then
        log_success "Rewrite rules include Bamero patterns"
    else
        log_warning "Rewrite rules may need to be flushed"
    fi
    
    return 0
}

# 12. Check file permissions
check_permissions() {
    log_info "Checking file permissions..."
    
    cd "$PROJECT_ROOT"
    
    # Check wp-config.php
    if [ -f "wp-config.php" ]; then
        WP_CONFIG_PERMS=$(stat -c "%a" wp-config.php 2>/dev/null)
        if [ "$WP_CONFIG_PERMS" = "440" ] || [ "$WP_CONFIG_PERMS" = "400" ]; then
            log_success "wp-config.php permissions: $WP_CONFIG_PERMS (secure)"
        else
            log_warning "wp-config.php permissions: $WP_CONFIG_PERMS (should be 440 or 400)"
        fi
    fi
    
    # Check .env
    if [ -f ".env" ]; then
        ENV_PERMS=$(stat -c "%a" .env 2>/dev/null)
        if [ "$ENV_PERMS" = "400" ] || [ "$ENV_PERMS" = "440" ]; then
            log_success ".env permissions: $ENV_PERMS (secure)"
        else
            log_warning ".env permissions: $ENV_PERMS (should be 400 or 440)"
        fi
    fi
    
    return 0
}

# 13. Check ZarinPal configuration
check_zarinpal() {
    log_info "Checking ZarinPal configuration..."
    
    cd "$PROJECT_ROOT"
    
    ZARINPAL_ENABLED=$(wp option get woocommerce_bamero_zarinpal_settings 2>/dev/null | grep -o '"enabled":"[^"]*"' | cut -d'"' -f4)
    if [ "$ZARINPAL_ENABLED" = "yes" ]; then
        log_success "ZarinPal gateway: Enabled"
    else
        log_error "ZarinPal gateway: Disabled"
        return 1
    fi
    
    # Check merchant ID in environment
    if [ -n "$ZARINPAL_MERCHANT_ID" ]; then
        log_success "ZarinPal Merchant ID: Set"
    else
        log_error "ZarinPal Merchant ID: Not set in environment"
        return 1
    fi
    
    return 0
}

# 14. Check SMS configuration
check_sms() {
    log_info "Checking SMS configuration..."
    
    cd "$PROJECT_ROOT"
    
    SMS_PROVIDER=${SMS_PROVIDER:-not_set}
    if [ "$SMS_PROVIDER" = "sms_ir" ]; then
        log_success "SMS Provider: SMS.ir"
    else
        log_error "SMS Provider: Not configured (Current: $SMS_PROVIDER)"
        return 1
    fi
    
    if [ -n "$SMS_IR_API_KEY" ]; then
        log_success "SMS.ir API Key: Set"
    else
        log_error "SMS.ir API Key: Not set in environment"
        return 1
    fi
    
    return 0
}

# 15. Check HPOS compatibility
check_hpos() {
    log_info "Checking HPOS compatibility..."
    
    cd "$PROJECT_ROOT"
    
    # Check if HPOS is declared compatible
    HPOS_COMPATIBLE=$(wp db query "SELECT option_value FROM wp_options WHERE option_name = 'woocommerce_custom_order_tables_enabled'" 2>/dev/null)
    if [ "$HPOS_COMPATIBLE" = "yes" ]; then
        log_success "HPOS: Enabled and compatible"
    else
        log_warning "HPOS may not be enabled or compatible"
    fi
    
    return 0
}

# Main validation function
main() {
    echo ""
    echo "=========================================="
    echo "   Bamero Pre-Go-Live Validation"
    echo "=========================================="
    echo ""
    
    check_wpcli || { log_error "WP-CLI check failed"; exit 1; }
    
    # Run all checks
    check_php_version
    check_php_extensions
    check_wordpress
    check_woocommerce
    check_bamero_plugins
    check_required_pages
    check_wc_pages
    check_database
    check_environment
    check_cron
    check_rewrite
    check_permissions
    check_zarinpal
    check_sms
    check_hpos
    
    echo ""
    echo "=========================================="
    echo "   Validation Summary"
    echo "=========================================="
    echo -e "${GREEN}Passed: $PASS_COUNT${NC}"
    echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
    echo -e "${RED}Failed: $FAIL_COUNT${NC}"
    echo ""
    
    if [ $ERRORS -eq 0 ]; then
        echo -e "${GREEN}🎉 All validations passed! Ready for Go-Live.${NC}"
        exit 0
    else
        echo -e "${RED}❌ $ERRORS validation errors found. Fix them before Go-Live.${NC}"
        exit 1
    fi
}

# Run main
main "$@"
