# =============================================================================
# Bamero Makefile - Common Development and Deployment Commands
# =============================================================================
# Usage:
#   make help           - Show this help message
#   make install        - Install WordPress + WooCommerce
#   make setup          - Setup Bamero plugins and settings
#   make deploy         - Deploy to production
#   make validate       - Validate configuration
#   make test           - Run all tests
#   make db-backup      - Backup database
#   make db-restore     - Restore database from backup
#   make reset          - Reset WordPress (DANGER: deletes everything!)
#   make rollback       - Rollback to previous commit
#   make docker-up      - Start Docker containers
#   make docker-down    - Stop Docker containers
# =============================================================================

.PHONY: help install setup deploy validate test db-backup db-restore reset rollback docker-up docker-down

# =============================================================================
# Help
# =============================================================================
help:
	@echo "Bamero Makefile - Available Commands:"
	@echo "====================================="
	@echo ""
	@echo "  Development:"
	@echo "    make install        - Install WordPress + WooCommerce"
	@echo "    make setup          - Setup Bamero plugins and settings"
	@echo "    make test           - Run all tests"
	@echo "    make validate       - Validate configuration"
	@echo ""
	@echo "  Database:"
	@echo "    make db-backup      - Backup database"
	@echo "    make db-restore     - Restore database from latest backup"
	@echo ""
	@echo "  Deployment:"
	@echo "    make deploy         - Deploy to production"
	@echo "    make rollback       - Rollback to previous commit"
	@echo ""
	@echo "  Docker:"
	@echo "    make docker-up      - Start Docker containers"
	@echo "    make docker-down    - Stop Docker containers"
	@echo ""
	@echo "  Dangerous:"
	@echo "    make reset          - Reset WordPress (DANGER: deletes everything!)"
	@echo ""

# =============================================================================
# Install WordPress + WooCommerce
# =============================================================================
install:
	@echo "🔹 Installing WordPress + WooCommerce..."
	wp core download --locale=fa_IR
	wp config create --dbname=${DB_NAME} --dbuser=${DB_USER} --dbpass=${DB_PASS} --dbhost=${DB_HOST} --locale=fa_IR --skip-check
	wp core install --url="http://localhost:8080" --title="بامرو" --admin_user="admin" --admin_password="admin" --admin_email="admin@bamero.test" --locale=fa_IR --skip-email
	wp plugin install woocommerce --activate
	wp plugin activate woocommerce
	@echo "✅ WordPress + WooCommerce installed"

# =============================================================================
# Setup Bamero
# =============================================================================
setup: install
	@echo "🔹 Setting up Bamero..."
	wp plugin activate bamero-zarinpal-gateway bamero-mobile-auth bamero-production-core bamero-woocommerce-setup bamero-essential-plugins bamero-custom-plugin
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
	@echo "🔹 Creating required pages..."
	wp post create --post_type=page --post_title="فروشگاه" --post_status=publish --post_name="shop" --porcelain | xargs -I {} wp option update woocommerce_shop_page_id {}
	wp post create --post_type=page --post_title="سبد خرید" --post_status=publish --post_name="cart" --porcelain | xargs -I {} wp option update woocommerce_cart_page_id {}
	wp post create --post_type=page --post_title="تکمیل سفارش" --post_status=publish --post_name="checkout" --porcelain | xargs -I {} wp option update woocommerce_checkout_page_id {}
	wp post create --post_type=page --post_title="حساب کاربری" --post_status=publish --post_name="my-account" --porcelain | xargs -I {} wp option update woocommerce_myaccount_page_id {}
	wp post create --post_type=page --post_title="مشاوره رنگ" --post_status=publish --post_name="consultation"
	wp post create --post_type=page --post_title="درباره ما" --post_status=publish --post_name="about"
	wp post create --post_type=page --post_title="تماس با ما" --post_status=publish --post_name="contact"
	wp post create --post_type=page --post_title="حریم خصوصی" --post_status=publish --post_name="privacy-policy"
	wp post create --post_type=page --post_title="شرایط استفاده" --post_status=publish --post_name="terms-of-service"
	wp rewrite flush
	wp cache flush
	@echo "✅ Bamero setup complete"

# =============================================================================
# Deploy to Production
# =============================================================================
deploy:
	@echo "🔹 Deploying to production..."
	./scripts/deploy.sh production

# =============================================================================
# Validate Configuration
# =============================================================================
validate:
	@echo "🔹 Validating configuration..."
	./scripts/validate.sh

# =============================================================================
# Run Tests
# =============================================================================
test:
	@echo "🔹 Running tests..."
	@echo ""
	@echo "=== PHP Lint ==="
	find . -type f -name "*.php" -not -path "./docs/*" -not -path "./vendor/*" | xargs -n 1 php -l 2>/dev/null || true
	@echo ""
	@echo "=== WooCommerce Tests ==="
	wp db check
	wp cron test
	@echo ""
	@echo "✅ All tests passed"

# =============================================================================
# Database Backup
# =============================================================================
db-backup:
	@echo "🔹 Creating database backup..."
	mkdir -p backups
	mysqldump -u ${DB_USER} -p${DB_PASS} ${DB_NAME} > backups/bamero_db_$$(date +%Y%m%d_%H%M%S).sql
	@echo "✅ Database backup created: backups/bamero_db_$$(date +%Y%m%d_%H%M%S).sql"

# =============================================================================
# Database Restore
# =============================================================================
db-restore:
	@echo "🔹 Restoring database from latest backup..."
	LATEST_BACKUP=$(ls -t backups/bamero_db_*.sql 2>/dev/null | head -1)
	if [ -n "$${LATEST_BACKUP}" ]; then
		mysql -u ${DB_USER} -p${DB_PASS} ${DB_NAME} < "$${LATEST_BACKUP}"
		@echo "✅ Database restored from: $${LATEST_BACKUP}"
	else
		@echo "❌ No backup found"
	fi

# =============================================================================
# Reset WordPress (DANGER!)
# =============================================================================
reset:
	@echo "⚠️  This will DELETE all WordPress data!"
	@read -p "Are you sure? (y/N) " -n 1 -r; echo; if [[ ! $$REPLY =~ ^[Yy]$$ ]]; then exit 1; fi
	wp db reset --yes
	wp plugin delete --all --yes
	wp theme delete --all --yes
	rm -rf wp-content/uploads/*
	@echo "✅ WordPress reset complete"

# =============================================================================
# Rollback
# =============================================================================
rollback:
	@echo "🔹 Rolling back..."
	./scripts/rollback.sh

# =============================================================================
# Docker Commands
# =============================================================================
docker-up:
	@echo "🔹 Starting Docker containers..."
	docker-compose up -d
	@echo "✅ Docker containers started"
	@echo "   WordPress: http://localhost:8080"
	@echo "   phpMyAdmin: http://localhost:8081"
	@echo "   MailHog: http://localhost:8025"


docker-down:
	@echo "🔹 Stopping Docker containers..."
	docker-compose down
	@echo "✅ Docker containers stopped"

# =============================================================================
# Clean
# =============================================================================
clean:
	@echo "🔹 Cleaning up..."
	rm -rf backups/*
	rm -rf wp-content/cache/*
	wp cache flush
	@echo "✅ Cleanup complete"

# =============================================================================
# Health Check
# =============================================================================
health:
	@echo "🔹 Running health check..."
	curl -s http://localhost:8080/health-check.php | jq .

# =============================================================================
# Logs
# =============================================================================
logs:
	@echo "🔹 Showing logs..."
	docker-compose logs -f --tail=50

# =============================================================================
