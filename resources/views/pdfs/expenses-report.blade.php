<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Expenses Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        .muted { color: #64748b; }
        .summary { width: 100%; margin: 18px 0; border-collapse: separate; border-spacing: 8px 0; }
        .summary td { border: 1px solid #cbd5e1; padding: 10px; }
        .summary strong { display: block; margin-top: 4px; font-size: 16px; }
        table.report { width: 100%; border-collapse: collapse; }
        table.report th, table.report td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
        table.report th { background: #f1f5f9; text-align: left; }
        .right { text-align: right; }
        .footer { margin-top: 12px; color: #64748b; font-size: 9px; }
    </style>
</head>
<body>
    <h1>{{ $settings['company_name'] ?? config('app.name', 'MuebleDesk') }} — Expenses Report</h1>
    <div class="muted">Generated {{ now()->format('Y-m-d H:i') }}</div>
    @if (!empty($filters['from']) || !empty($filters['to']) || !empty($filters['category']) || !empty($filters['q']))
        <div class="muted" style="margin-top: 4px;">
            Filters:
            @if (!empty($filters['from'])) from {{ $filters['from'] }} @endif
            @if (!empty($filters['to'])) to {{ $filters['to'] }} @endif
            @if (!empty($filters['category'])) · category {{ str($filters['category'])->replace('_', ' ')->title() }} @endif
            @if (!empty($filters['q'])) · search “{{ $filters['q'] }}” @endif
        </div>
    @endif

    <table class="summary">
        <tr>
            <td>Filtered Spend<strong>RM {{ number_format($summary['total'], 2) }}</strong></td>
            <td>Tax Deductible<strong>RM {{ number_format($summary['tax_deductible'], 2) }}</strong></td>
            <td>Billable / Rechargeable<strong>RM {{ number_format($summary['billable'], 2) }}</strong></td>
        </tr>
    </table>

    <table class="report">
        <thead>
            <tr>
                <th>Date</th><th>Expense #</th><th>Category</th><th>Vendor</th><th>Description</th><th>Payment</th><th>Reference</th><th>Tax Deductible</th><th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ optional($expense->expense_date)->format('Y-m-d') }}</td>
                    <td>{{ $expense->expense_number }}</td>
                    <td>{{ str($expense->category)->replace('_', ' ')->title() }}</td>
                    <td>{{ $expense->vendor ?: '—' }}</td>
                    <td>{{ $expense->description }}</td>
                    <td>{{ $expense->payment_method ?: '—' }}</td>
                    <td>{{ $expense->reference_number ?: '—' }}</td>
                    <td>{{ $expense->is_tax_deductible ? 'Yes' : 'No' }}</td>
                    <td class="right">{{ $expense->currency }} {{ number_format((float) $expense->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No expenses found for the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Exported from MuebleDesk.</div>
</body>
</html>
