<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Profit & Loss Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        h1 { margin: 0 0 4px; font-size: 20px; }
        h2 { margin: 18px 0 8px; font-size: 14px; }
        .muted { color: #64748b; }
        .summary { width: 100%; margin: 18px 0; border-collapse: separate; border-spacing: 8px 0; }
        .summary td { border: 1px solid #cbd5e1; padding: 10px; }
        .summary strong { display: block; margin-top: 4px; font-size: 16px; }
        table.report { width: 100%; border-collapse: collapse; }
        table.report th, table.report td { border: 1px solid #cbd5e1; padding: 6px; }
        table.report th { background: #f1f5f9; text-align: left; }
        .right { text-align: right; }
        .positive { color: #047857; }
        .negative { color: #b91c1c; }
        .footer { margin-top: 12px; color: #64748b; font-size: 9px; }
    </style>
</head>
<body>
    <h1>{{ $settings['company_name'] ?? config('app.name', 'MuebleDesk') }} — Profit & Loss Report</h1>
    <div class="muted">
        {{ $period === 'all_time' ? 'All Time' : ($month ? $rangeStart->format('F Y') : (string) $year) }}
        · {{ $rangeStart->format('Y-m-d') }} to {{ $rangeEnd->format('Y-m-d') }}
        · Generated {{ now()->format('Y-m-d H:i') }}
    </div>

    <table class="summary">
        <tr>
            <td>Revenue Collected<strong>RM {{ number_format((float) $revenue, 2) }}</strong></td>
            <td>Company Expenses<strong>RM {{ number_format((float) $expenses, 2) }}</strong></td>
            <td>Net Profit / Loss<strong class="{{ $netProfit >= 0 ? 'positive' : 'negative' }}">RM {{ number_format((float) $netProfit, 2) }}</strong></td>
        </tr>
    </table>

    <h2>Expenses by Category</h2>
    <table class="report">
        <thead><tr><th>Category</th><th class="right">Amount</th></tr></thead>
        <tbody>
            @forelse ($expensesByCategory as $row)
                <tr>
                    <td>{{ str($row->category)->replace('_', ' ')->title() }}</td>
                    <td class="right">RM {{ number_format((float) $row->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2">No expenses in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>{{ $period === 'all_time' ? 'All-Time Monthly Breakdown' : 'Monthly Breakdown' }}</h2>
    <table class="report">
        <thead><tr><th>Month</th><th class="right">Revenue</th><th class="right">Expenses</th><th class="right">Net Profit / Loss</th></tr></thead>
        <tbody>
            @foreach ($monthlyProfitLoss as $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td class="right">RM {{ number_format($row['revenue'], 2) }}</td>
                    <td class="right">RM {{ number_format($row['expenses'], 2) }}</td>
                    <td class="right {{ $row['net_profit'] >= 0 ? 'positive' : 'negative' }}">RM {{ number_format($row['net_profit'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Exported from MuebleDesk.</div>
</body>
</html>
