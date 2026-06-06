#!/bin/sh
set -e

echo "=== Starting Haraj App ==="

# Run database migrations
echo ">>> Running migrations..."
php artisan migrate --force

# Cache config/routes/views for production performance
echo ">>> Warming caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage symlink
echo ">>> Creating storage symlink..."
php artisan storage:link || true

# Seed admin user (safe — skips if already exists)
echo ">>> Seeding admin user..."
php artisan db:seed --class=AdminSeeder --force || true

echo ">>> Starting PHP-FPM and Nginx via supervisord..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
