<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; margin: 28px; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 18px; margin-bottom: 24px; }
        .title { font-size: 24px; font-weight: 700; margin: 0 0 5px; }
        .muted { color: #64748b; }
        .grid { width: 100%; margin-bottom: 24px; }
        .grid td { width: 50%; vertical-align: top; padding: 6px 0; }
        .label { color: #64748b; font-size: 10px; text-transform: uppercase; }
        .value { font-size: 13px; font-weight: 700; margin-top: 3px; }
        .amount { background: #f8fafc; border: 1px solid #e2e8f0; padding: 18px; margin: 18px 0 24px; }
        .amount strong { font-size: 22px; }
        .footer { border-top: 1px solid #e2e8f0; margin-top: 30px; padding-top: 14px; color: #64748b; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">MuebleDesk Payment Receipt</p>
        <div class="muted">Receipt for a confirmed SaaS subscription payment</div>
    </div>

    <table class="grid" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <div class="label">Billed to</div>
                <div class="value">{{ $company->name }}</div>
                @if($company->email)<div class="muted">{{ $company->email }}</div>@endif
            </td>
            <td>
                <div class="label">Payment date</div>
                <div class="value">{{ ($payment->paid_at ?? $payment->created_at)?->format('d M Y H:i') ?? '—' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Plan</div>
                <div class="value">{{ $payment->plan?->name ?? $payment->description ?? 'MuebleDesk subscription' }}</div>
            </td>
            <td>
                <div class="label">Payment status</div>
                <div class="value">PAID</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Stripe invoice reference</div>
                <div class="value">{{ $payment->provider_invoice_id ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Payment reference</div>
                <div class="value">{{ $payment->provider_payment_id ?? 'Recorded by Stripe invoice webhook' }}</div>
            </td>
        </tr>
    </table>

    <div class="amount">
        <div class="label">Amount paid</div>
        <strong>{{ strtoupper($payment->currency) }} {{ number_format((float) $payment->amount, 2) }}</strong>
    </div>

    @if($payment->description)
        <div class="label">Description</div>
        <div class="value">{{ $payment->description }}</div>
    @endif

    <div class="footer">
        This receipt is generated from MuebleDesk's subscription payment record after the payment was confirmed by the configured billing provider. The original Stripe invoice remains available separately in the Client Portal.
    </div>
</body>
</html>
