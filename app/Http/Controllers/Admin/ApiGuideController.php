<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\View\View;

class ApiGuideController extends Controller
{
    public function index(): View
    {
        return view('admin.api-guide.index', [
            'baseUrl' => url('/api/v1'),
            'permissions' => ApiKey::AVAILABLE_PERMISSIONS,
            'endpointGroups' => $this->endpointGroups(),
        ]);
    }

    private function endpointGroups(): array
    {
        return [
            'Clients' => [
                ['GET', '/clients', 'clients.read', 'List clients. Supports q and per_page.'],
                ['GET', '/clients/{client}', 'clients.read', 'Get one client.'],
                ['POST', '/clients', 'clients.write', 'Create a client.'],
                ['PUT/PATCH', '/clients/{client}', 'clients.write', 'Update a client.'],
                ['DELETE', '/clients/{client}', 'clients.delete', 'Delete a client when no dependent documents exist.'],
            ],
            'Invoices & Payments' => [
                ['GET', '/invoices', 'invoices.read', 'List invoices.'],
                ['GET', '/invoices/{invoice}', 'invoices.read', 'Get one invoice.'],
                ['POST', '/invoices', 'invoices.write', 'Create an invoice.'],
                ['PUT/PATCH', '/invoices/{invoice}', 'invoices.write', 'Update an invoice.'],
                ['DELETE', '/invoices/{invoice}', 'invoices.delete', 'Delete an eligible invoice.'],
                ['POST', '/invoices/{invoice}/payments', 'payments.write', 'Record a payment against an invoice.'],
                ['GET', '/payments', 'payments.read', 'List payments.'],
                ['GET', '/payments/{payment}', 'payments.read', 'Get one payment.'],
                ['DELETE', '/payments/{payment}', 'payments.delete', 'Delete a payment and recalculate invoice balance.'],
            ],
            'Quotations' => [
                ['GET', '/quotations', 'quotations.read', 'List quotations.'],
                ['GET', '/quotations/{quotation}', 'quotations.read', 'Get one quotation.'],
                ['POST', '/quotations', 'quotations.write', 'Create a quotation.'],
                ['PUT/PATCH', '/quotations/{quotation}', 'quotations.write', 'Update a quotation.'],
                ['DELETE', '/quotations/{quotation}', 'quotations.delete', 'Delete an eligible quotation.'],
            ],
            'Expenses & Reports' => [
                ['GET', '/expenses', 'expenses.read', 'List expenses.'],
                ['GET', '/expenses/{expense}', 'expenses.read', 'Get one expense.'],
                ['POST', '/expenses', 'expenses.write', 'Create an expense.'],
                ['PUT/PATCH', '/expenses/{expense}', 'expenses.write', 'Update an expense.'],
                ['DELETE', '/expenses/{expense}', 'expenses.delete', 'Delete an expense.'],
                ['GET', '/reports/profit-loss', 'reports.profit_loss', 'Return the Profit & Loss report.'],
            ],
            'Recurring Invoices' => [
                ['GET', '/recurring-invoices', 'recurring_invoices.read', 'List recurring invoices.'],
                ['GET', '/recurring-invoices/{recurringInvoice}', 'recurring_invoices.read', 'Get one recurring invoice.'],
                ['POST', '/recurring-invoices', 'recurring_invoices.write', 'Create a recurring invoice.'],
                ['PUT/PATCH', '/recurring-invoices/{recurringInvoice}', 'recurring_invoices.write', 'Update a recurring invoice.'],
                ['DELETE', '/recurring-invoices/{recurringInvoice}', 'recurring_invoices.delete', 'Delete a recurring invoice.'],
            ],
            'Administration' => [
                ['GET', '/users', 'users.read', 'List company users.'],
                ['POST', '/users', 'users.write', 'Create a company user.'],
                ['PUT/PATCH', '/users/{user}', 'users.write', 'Update a company user.'],
                ['GET', '/settings', 'settings.read', 'Read company settings.'],
                ['GET', '/activity-logs', 'activity_logs.read', 'Read company activity logs.'],
            ],
        ];
    }
}
