@php
    $user = Auth::user();
    $customerView = $user?->isCustomer() ?? false;
    $adminView = $user?->isAdmin() ?? false;
    $production = config('myinvois.environment') === 'production';
    $backRoute = $customerView ? route('invoices.customer_show', $invoice) : route('invoices.show', $invoice);
    $submitRoute = $customerView ? route('customer.einvoices.submit', $invoice) : route('einvoices.submit', $invoice);
    $refreshRoute = $customerView ? route('customer.einvoices.refresh', $invoice) : route('einvoices.refresh', $invoice);
    $displayStatus = strtoupper(str_replace('_', ' ', $eInvoice?->status ?? 'NOT SUBMITTED'));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">e-Invoice — {{ $invoice->invoice_number }}</h2>
            <a href="{{ $backRoute }}" class="text-sm font-semibold text-indigo-600">Back to invoice</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            @if ($adminView && $production)
                <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                    <strong>Production MyInvois:</strong> This submission creates an official e-Invoice. Verify the buyer, invoice total, and tax before submitting.
                </div>
            @endif

            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-gray-800">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Current status</p>
                        <p class="mt-1 text-2xl font-extrabold text-slate-950 dark:text-white">{{ $displayStatus }}</p>
                    </div>
                    @if ($adminView)
                        <div class="grid grid-cols-3 gap-3 text-right text-xs">
                            <div><span class="block text-slate-400">Environment</span><strong>{{ strtoupper($check['environment']) }}</strong></div>
                            <div><span class="block text-slate-400">Version</span><strong>{{ $check['document_version'] }}</strong></div>
                            <div><span class="block text-slate-400">Readiness</span><strong class="{{ $check['ready'] ? 'text-emerald-600' : 'text-red-600' }}">{{ $check['ready'] ? 'READY' : 'NOT READY' }}</strong></div>
                        </div>
                    @endif
                </div>

                <div class="mt-6 grid gap-4 rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-900 md:grid-cols-2 lg:grid-cols-4">
                    <div><span class="block text-xs uppercase text-slate-500">Buyer</span><strong>{{ $invoice->client->name }}</strong></div>
                    <div><span class="block text-xs uppercase text-slate-500">TIN</span><strong>{{ $invoice->client->tin_number }}</strong></div>
                    <div><span class="block text-xs uppercase text-slate-500">Invoice total</span><strong>RM {{ number_format($invoice->total_amount, 2) }}</strong></div>
                    <div><span class="block text-xs uppercase text-slate-500">Tax</span><strong>RM {{ number_format($invoice->tax_amount, 2) }}</strong></div>
                </div>

                @if (! $check['ready'] && (! $eInvoice || $canRetry))
                    <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
                        <h3 class="font-semibold text-red-900">Some details need to be completed</h3>
                        @if ($adminView)
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-800">@foreach ($check['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                        @else
                            <p class="mt-2 text-sm text-red-800">Please update the e-Invoice profile or contact support before trying again.</p>
                        @endif
                        @if ($customerView)<a href="{{ route('customer.einvoice-profile.edit') }}" class="mt-4 inline-flex rounded-md bg-red-700 px-4 py-2 text-xs font-semibold uppercase text-white">Update e-Invoice profile</a>@endif
                    </div>
                @endif

                @if ($eInvoice)
                    <div class="mt-6 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        @if ($adminView)
                            <h3 class="font-semibold dark:text-white">Submission details</h3>
                            <dl class="mt-3 grid gap-3 text-sm md:grid-cols-4">
                                <div><dt class="text-gray-500">Submission UID</dt><dd class="break-all font-mono dark:text-white">{{ $eInvoice->submission?->submission_uid ?: 'Pending' }}</dd></div>
                                <div><dt class="text-gray-500">Document UUID</dt><dd class="break-all font-mono dark:text-white">{{ $eInvoice->myinvois_uuid ?: 'Pending' }}</dd></div>
                                <div><dt class="text-gray-500">Attempts</dt><dd class="font-semibold dark:text-white">{{ $eInvoice->submission_attempts }}</dd></div>
                                <div><dt class="text-gray-500">Correlation ID</dt><dd class="break-all font-mono dark:text-white">{{ $eInvoice->correlation_id ?: '—' }}</dd></div>
                            </dl>
                            @if ($eInvoice->failure_reason)<div class="mt-4 whitespace-pre-wrap rounded bg-red-50 p-3 text-sm text-red-800">{{ $eInvoice->failure_reason }}</div>@endif
                            @if ($eInvoice->validation_errors)<pre class="mt-4 overflow-auto rounded bg-gray-950 p-4 text-xs text-red-300">{{ json_encode($eInvoice->validation_errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>@endif
                            @if ($eInvoice->status === 'reconciliation_required' && ! $canRetry)<div class="mt-4 rounded bg-amber-50 p-3 text-sm font-semibold text-amber-900">The submission outcome is uncertain and requires staff review before another attempt.</div>@endif
                        @elseif ($eInvoice->status === 'reconciliation_required' && ! $canRetry)
                            <p class="text-sm text-amber-800">This e-Invoice is being reviewed. Please contact support if you need assistance.</p>
                        @endif

                        @if ($eInvoice->submission?->submission_uid && ! in_array($eInvoice->status, ['valid', 'invalid', 'cancelled'], true))
                            <form class="mt-4" action="{{ $refreshRoute }}" method="POST">@csrf<button class="rounded-md bg-slate-700 px-4 py-2 text-xs font-semibold uppercase text-white">Refresh status</button></form>
                        @endif

                        @if ($eInvoice->validationUrl())
                            <div class="mt-6 grid gap-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 md:grid-cols-[220px_1fr] md:items-center">
                                <img src="{{ $eInvoice->qrCodeDataUri() }}" alt="Validated MyInvois e-Invoice QR code" class="h-[220px] w-[220px] rounded-xl bg-white p-2">
                                <div>
                                    <h4 class="text-lg font-extrabold text-emerald-900">Validated e-Invoice</h4>
                                    <p class="mt-2 text-sm text-emerald-800">Your official e-Invoice is ready.</p>
                                    <a target="_blank" rel="noopener" href="{{ $eInvoice->validationUrl() }}" class="mt-4 inline-flex rounded-md bg-green-600 px-4 py-2 text-xs font-semibold uppercase text-white">Open e-Invoice</a>
                                    @if ($adminView && $eInvoice->canCancel())
                                        <form class="mt-5 space-y-2" action="{{ route('einvoices.cancel', $invoice) }}" method="POST" onsubmit="return confirm('Cancel this valid e-Invoice in MyInvois?');">
                                            @csrf @method('PUT')
                                            <label class="block text-sm font-bold text-red-900">Cancellation reason</label>
                                            <input name="reason" maxlength="300" required class="block w-full" placeholder="Example: Wrong buyer details">
                                            <p class="text-xs text-red-800">Available until {{ $eInvoice->cancellationDeadline()?->format('Y-m-d H:i:s') }}.</p>
                                            <button class="rounded-md bg-red-700 px-4 py-2 text-xs font-semibold uppercase text-white">Cancel e-Invoice</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @elseif (! $canRetry && ! $adminView)
                            <p class="mt-4 text-sm font-semibold text-slate-600">Your e-Invoice is being processed.</p>
                        @endif
                    </div>
                @endif
            </div>

            @if ($payload && (! $eInvoice || $canRetry))
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-gray-800">
                    <h3 class="text-lg font-semibold dark:text-white">Submit e-Invoice</h3>
                    <p class="mt-1 text-sm text-gray-500">Please confirm the buyer and invoice details above before submitting.</p>
                    <form class="mt-5 space-y-4" action="{{ $submitRoute }}" method="POST">
                        @csrf
                        @if ($adminView && $production)
                            <label class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-950"><input type="checkbox" name="confirm_live_submission" value="1" required> I have checked the buyer, amount, and tax details.</label>
                        @endif
                        <button {{ config('myinvois.enabled') ? '' : 'disabled' }} class="rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:bg-gray-400">{{ $canRetry ? 'Try Again' : 'Submit e-Invoice' }}</button>
                    </form>
                    @if ($adminView)<p class="mt-4 text-xs text-slate-400">Payload hash: <span class="font-mono">{{ $payload['hash'] }}</span></p>@endif
                    @if ($adminView)<pre class="mt-6 max-h-[48rem] overflow-auto rounded-lg bg-gray-950 p-5 text-xs leading-5 text-green-300">{{ json_encode($payload['document'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>@endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
