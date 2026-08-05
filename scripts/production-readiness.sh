#!/usr/bin/env bash
set -euo pipefail

failures=0

check_command() {
  local label="$1"
  shift
  printf '\n[%s]\n' "$label"
  if "$@"; then
    printf 'PASS: %s\n' "$label"
  else
    printf 'FAIL: %s\n' "$label"
    failures=$((failures + 1))
  fi
}

check_env_not_equal() {
  local key="$1"
  local forbidden="$2"
  local value
  value="$(php artisan tinker --execute="echo (string) config('${key}');" 2>/dev/null || true)"
  if [[ -n "$value" && "$value" != "$forbidden" ]]; then
    printf 'PASS: %s=%s\n' "$key" "$value"
  else
    printf 'FAIL: %s must not be %s or empty\n' "$key" "$forbidden"
    failures=$((failures + 1))
  fi
}

printf 'MuebleDesk production readiness checks\n'
printf '======================================\n'

check_command 'PHP version' php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'
check_command 'Composer dependency validation' composer validate --strict --no-check-publish
check_command 'Composer security audit' composer audit --locked --no-dev
check_command 'Application boots' php artisan about --only=environment
check_command 'Database migrations are current' php artisan migrate:status
check_command 'Automated test suite' php artisan test
check_command 'Route cache builds' php artisan route:cache
check_command 'Configuration cache builds' php artisan config:cache
check_command 'Blade view cache builds' php artisan view:cache
check_command 'Scheduler is registered' php artisan schedule:list

check_env_not_equal 'queue.default' 'sync'
check_env_not_equal 'app.debug' '1'

printf '\nManual evidence required before production approval:\n'
printf '  - queue worker managed by Supervisor/systemd and observed processing a real queued job\n'
printf '  - cron invokes php artisan schedule:run every minute\n'
printf '  - Stripe test-mode checkout, success, failure, retry and cancellation completed\n'
printf '  - database and storage backups restored into an isolated environment\n'
printf '  - cross-tenant acceptance test completed using two companies and separate users\n'
printf '  - MyInvois sandbox lifecycle completed successfully\n'
printf '  - SMTP verification, password reset and notification delivery confirmed\n'

if (( failures > 0 )); then
  printf '\nProduction gate FAILED with %d automated failure(s).\n' "$failures"
  exit 1
fi

printf '\nAutomated production gates passed. Manual evidence is still required.\n'
