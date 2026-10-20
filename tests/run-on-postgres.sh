#!/usr/bin/env bash
# Runs the whole test suite against REAL PostgreSQL, in an isolated schema called "phpunit" that it creates and wipes.
# Your actual data (the "public" schema) is never touched.
#
# Why this exists: the normal suite runs on in-memory SQLite, which accepts SQL that Postgres rejects (for example
# GROUP BY together with ORDER BY on another column). A page can pass every test and still return a 500 in production.
#
#   tests/run-on-postgres.sh [phpunit/artisan test arguments]
#
# Notes: it passes ONLY the database settings from .env. Exporting the whole .env would override the test environment
# (array sessions, no CSRF checks) and make everything fail with 419. Do not run it at the same time as the normal
# suite: both use the same fake-disk folder and wipe each other's files.
set -euo pipefail
cd "$(dirname "$0")/.."
g() { grep -E "^$1=" .env | head -1 | cut -d= -f2- | tr -d '"'; }
export PGPASSWORD="$(g DB_PASSWORD)"
psql -h "$(g DB_HOST)" -p "$(g DB_PORT)" -U "$(g DB_USERNAME)" -d "$(g DB_DATABASE)" -Atc "drop schema if exists phpunit cascade; create schema phpunit;" >/dev/null
exec env DB_CONNECTION=pgsql DB_SEARCH_PATH=phpunit DB_DATABASE="$(g DB_DATABASE)" DB_HOST="$(g DB_HOST)" DB_PORT="$(g DB_PORT)" \
  DB_USERNAME="$(g DB_USERNAME)" DB_PASSWORD="$(g DB_PASSWORD)" php artisan test "$@"
