# Mueble Desk API v1

The Mueble Desk API exposes production endpoints for integrations such as n8n, Zapier, mobile apps, reporting tools, and external business systems.

Base URL:

```text
https://your-domain.com/api/v1
```

## Authentication

Create an API key from:

```text
Admin → API Keys
/admin/api-keys
```

API keys are shown only once. Store them securely.

Send the key using either header:

```bash
Authorization: Bearer mdk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

or:

```bash
X-API-Key: mdk_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

## Security model

API keys are stored as SHA-256 hashes. Plain keys are never stored.

Each key supports:

- permission scopes
- optional owner user
- optional expiry date
- optional IP allowlist
- revocation
- last-used timestamp
- activity logs for key creation/revocation/deletion

## Permissions

Available permissions:

```text
*
clients.read
clients.write
clients.delete
quotations.read
quotations.write
quotations.delete
invoices.read
invoices.write
invoices.delete
payments.read
payments.write
payments.delete
expenses.read
expenses.write
expenses.delete
reports.profit_loss
recurring_invoices.read
recurring_invoices.write
recurring_invoices.delete
users.read
users.write
settings.read
activity_logs.read
```

Use `*` only for trusted internal server-to-server integrations.

## Standard response format

List endpoints:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 100,
    "last_page": 4
  }
}
```

Create/update/show endpoints:

```json
{
  "data": {}
}
```

## Health check

```bash
curl https://your-domain.com/api/v1/health
```

## Clients

### List clients

Requires: `clients.read`

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/clients?q=acme&per_page=25"
```

### Create client

Requires: `clients.write`

```bash
curl -X POST "https://your-domain.com/api/v1/clients" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Acme Sdn Bhd",
    "client_type": "company",
    "email": "billing@acme.test",
    "billing_email": "accounts@acme.test",
    "phone": "+60312345678",
    "tin_number": "C1234567890",
    "id_type": "brn",
    "id_number": "202401234567",
    "address_line_1": "Level 1, Example Street",
    "city": "Kuala Lumpur",
    "state": "WP Kuala Lumpur",
    "postcode": "50000",
    "country_code": "MY",
    "payment_terms_days": 14
  }'
```

### Update client

Requires: `clients.write`

```bash
curl -X PATCH "https://your-domain.com/api/v1/clients/1" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name":"Acme Malaysia Sdn Bhd","email":"billing@acme.test"}'
```

### Delete client

Requires: `clients.delete`

```bash
curl -X DELETE "https://your-domain.com/api/v1/clients/1" \
  -H "Authorization: Bearer $API_KEY"
```

Clients with invoices or quotations cannot be deleted.

## Invoices

### List invoices

Requires: `invoices.read`

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/invoices?status=pending&client_id=1"
```

### Create invoice

Requires: `invoices.write`

```bash
curl -X POST "https://your-domain.com/api/v1/invoices" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 1,
    "date": "2026-07-16",
    "due_date": "2026-07-30",
    "discount_type": "fixed",
    "discount_value": 0,
    "tax_type": "sst",
    "tax_rate": 8,
    "items": [
      {"item_name": "Website Maintenance", "description": "Monthly plan", "quantity": 1, "price": 300}
    ]
  }'
```

The API calculates totals server-side, generates invoice numbers using the same sequence settings as the web app, and creates a payment link using the selected gateway when enabled.

### Record payment for invoice

Requires: `payments.write`

```bash
curl -X POST "https://your-domain.com/api/v1/invoices/1/payments" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 300,
    "payment_date": "2026-07-16",
    "payment_method": "bank_transfer",
    "transaction_reference": "MBB123456"
  }'
```

Recording a payment generates a payment receipt, updates invoice paid/partially-paid status, and locks the invoice.

## Quotations

Requires `quotations.read`, `quotations.write`, or `quotations.delete` depending on action.

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/quotations"
```

Create quotation payload is similar to invoice, using `expiry_date` instead of `due_date`.

## Payments

Requires: `payments.read`

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/payments?invoice_id=1"
```

Delete payment requires `payments.delete`. The invoice balance is recalculated after deletion.

## Expenses

Expense endpoints are admin/employee business endpoints protected by API key permissions.

### List expenses

Requires: `expenses.read`

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/expenses?category=software&from=2026-01-01&to=2026-12-31"
```

### Create expense

Requires: `expenses.write`

```bash
curl -X POST "https://your-domain.com/api/v1/expenses" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "expense_date": "2026-07-16",
    "category": "software",
    "vendor": "Adobe",
    "description": "Creative Cloud subscription",
    "amount": 250,
    "currency": "MYR",
    "payment_method": "card",
    "reference_number": "INV-ADOBE-001",
    "is_billable": false,
    "is_tax_deductible": true,
    "notes": "Monthly design software"
  }'
```

Categories:

```text
hosting, software, salary, contractor, marketing, office, transport, utilities, bank_fees, payment_gateway_fees, tax, equipment, professional_services, other
```

### Profit & loss report

Requires: `reports.profit_loss`

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/reports/profit-loss?year=2026"
```

Revenue is calculated from recorded payments. Expenses are calculated from the expenses table. Net profit is revenue minus expenses.

## Recurring invoices

Requires: `recurring_invoices.read`

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/recurring-invoices"
```

Recurring invoice write/delete endpoints are also available using `recurring_invoices.write` and `recurring_invoices.delete`.

## Users

Requires: `users.read`

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/users"
```

Sensitive fields like password, remember tokens, and 2FA secrets are not returned.

## Settings

Requires: `settings.read`

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/settings"
```

Sensitive gateway secrets are redacted/omitted.

## Activity logs

Requires: `activity_logs.read`

```bash
curl -H "Authorization: Bearer $API_KEY" \
  "https://your-domain.com/api/v1/activity-logs?event=invoice.created"
```

## Error responses

Missing key:

```json
{"message":"Missing API key. Use Authorization: Bearer <api_key> or X-API-Key."}
```

Insufficient permission:

```json
{"message":"This API key does not have permission: invoices.write"}
```

Validation error responses use Laravel's standard 422 format.

## Production recommendations

- Use HTTPS only.
- Prefer short-lived keys for third-party integrations.
- Use IP allowlists for server-to-server integrations.
- Do not use `*` unless necessary.
- Rotate keys periodically.
- Revoke unused keys.
- Monitor `/admin/activity-logs` and API key last-used timestamps.
