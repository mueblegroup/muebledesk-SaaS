# MuebleDesk Production Readiness

Production approval requires both automated gates and signed manual evidence. Passing CI alone is not production approval.

## Automated gates

Run:

```bash
composer production:check
```

Required automated results:

- PHP 8.3 or newer
- valid Composer metadata
- clean production dependency audit
- application boot
- current migrations
- complete PHPUnit suite
- route, configuration and Blade caches build successfully
- scheduler entries are registered
- production queue is not `sync`
- application debug mode is disabled

## Queue and scheduler evidence

1. Configure Redis or database queues.
2. Manage workers with Supervisor or systemd.
3. Dispatch a real queued email or notification.
4. Confirm the job is processed and no failed job is created.
5. Configure cron:

```cron
* * * * * cd /var/www/muebledesk-SaaS && php artisan schedule:run >> /dev/null 2>&1
```

6. Confirm scheduled commands execute in logs.

## Stripe acceptance

Complete in Stripe test mode:

- new checkout
- duplicate delivery of the same webhook event
- successful renewal
- failed renewal
- retry after failure
- cancellation at period end
- immediate cancellation where supported
- billing portal access
- payment ledger reconciliation

The `platform_webhook_events` table must contain one row per Stripe event ID. Replayed events must return HTTP 200 without repeating mutations.

## Tenancy acceptance

Use two companies and separate users. Verify that Company A cannot read, update, download or delete Company B resources by changing IDs or URLs for:

- clients
- quotations
- invoices
- payments and receipts
- expenses
- recurring invoices
- settings and API keys
- activity logs
- uploaded files
- e-Invoice records

## Backup and restoration drill

Back up:

- database
- `storage/app`
- encrypted environment configuration
- deployment revision/commit SHA

Restore into a clean isolated environment. Evidence must include:

- successful database import
- uploaded files available
- application key preserved
- login works
- invoice PDFs render
- queue worker starts
- scheduler runs
- one restored company can complete a normal workflow

A backup that has not been restored is not considered tested.

## Full staging acceptance

Complete the following on the production-like staging domain:

1. register and verify email
2. complete identity profile
3. create company
4. purchase plan in Stripe test mode
5. invite/administer users within plan limits
6. create client and quotation
7. convert quotation to invoice
8. record and reconcile payment
9. create recurring invoice
10. record expense and inspect reporting
11. complete MyInvois sandbox lifecycle
12. test customer portal
13. enable, challenge and recover 2FA
14. test password reset and email delivery
15. test mobile navigation and forms
16. perform backup restoration drill
17. execute rollback to the prior Git commit

## Release decision

Release only when:

- GitHub Production Gates workflow passes
- `composer audit --locked --no-dev` is clean
- all staging acceptance items are signed off
- backup restoration evidence exists
- rollback instructions have been tested
