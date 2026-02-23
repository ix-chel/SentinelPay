#!/bin/sh
set -e

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║       SentinelPay — Docker Test Run       ║"
echo "╚══════════════════════════════════════════╝"
echo ""

# Wait for Postgres to be fully ready
until pg_isready -h postgres -p 5432 -U "${DB_USERNAME:-sentinelpay}"; do
  echo "  Waiting for PostgreSQL..."
  sleep 2
done

echo "  ✓ PostgreSQL ready"

# Create the test database if it doesn't exist
PGPASSWORD="${DB_PASSWORD:-secret}" psql \
  -h postgres \
  -U "${DB_USERNAME:-sentinelpay}" \
  -tc "SELECT 1 FROM pg_database WHERE datname='sentinelpay_test'" \
  | grep -q 1 \
  || PGPASSWORD="${DB_PASSWORD:-secret}" psql \
     -h postgres \
     -U "${DB_USERNAME:-sentinelpay}" \
     -c "CREATE DATABASE sentinelpay_test;"

echo "  ✓ Test database ready"

# Run migrations on the test database
php artisan migrate --force

echo "  ✓ Migrations applied"
echo ""

# Run the full Pest suite
exec php artisan test "$@"
