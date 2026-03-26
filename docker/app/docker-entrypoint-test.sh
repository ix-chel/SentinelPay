#!/bin/sh
set -e

echo ""
echo "=============================================="
echo "       SentinelPay PostgreSQL Test Run        "
echo "=============================================="
echo ""

until pg_isready -h postgres -p 5432 -U "${DB_USERNAME:-sentinelpay}"; do
  echo "  Waiting for PostgreSQL..."
  sleep 2
done

echo "  PostgreSQL ready"

PGPASSWORD="${DB_PASSWORD:-secret}" psql \
  -h postgres \
  -U "${DB_USERNAME:-sentinelpay}" \
  -tc "SELECT 1 FROM pg_database WHERE datname='sentinelpay_test'" \
  | grep -q 1 \
  || PGPASSWORD="${DB_PASSWORD:-secret}" psql \
     -h postgres \
     -U "${DB_USERNAME:-sentinelpay}" \
     -c "CREATE DATABASE sentinelpay_test;"

echo "  Test database ready"

php artisan migrate:fresh --force

echo "  Migrations applied"
echo ""

exec php artisan test "$@"
