{{-- resources/views/public/payment_confirmation.blade.php --}}

<x-guest-layout> {{-- Or just a basic HTML structure without full app layout --}}
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg text-center">

            <h1 class="text-2xl font-bold mb-6">Payment Confirmation</h1>

            @if ($status === 'completed' || $status === 'succeeded')
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline">Your payment has been successfully processed. Thank you for your payment!</span>
                </div>
                <p class="text-gray-700 mb-4">
                    Invoice number: {{ $invoice->invoice_number ?? 'N/A' }}
                    @if ($reference)
                        <br>Reference ID: {{ $reference }}
                    @endif
                </p>
                <p class="text-gray-700 mb-6">
                    Your invoice status will be updated shortly.
                </p>
            @elseif ($status === 'failed')
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Payment Failed!</strong>
                    <span class="block sm:inline">Unfortunately, your payment could not be processed. Please try again.</span>
                </div>
                <p class="text-gray-700 mb-4">
                    @if ($invoice)
                        Invoice number: {{ $invoice->invoice_number }}
                    @else
                        Invoice Reference: {{ $reference ?? 'N/A' }}
                    @endif
                </p>
                <p class="text-gray-700 mb-6">
                    If the issue persists, please contact support.
                </p>
            @elseif ($status === 'pending')
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Payment Pending!</strong>
                    <span class="block sm:inline">Your payment is currently pending. Please check back later for status updates.</span>
                </div>
                <p class="text-gray-700 mb-4">
                    @if ($invoice)
                        Invoice number: {{ $invoice->invoice_number }}
                    @else
                        Invoice Reference: {{ $reference ?? 'N/A' }}
                    @endif
                </p>
                <p class="text-gray-700 mb-6">
                    You will receive an email notification once your payment is confirmed.
                </p>
            @else
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Payment Status Unknown.</strong>
                    <span class="block sm:inline">We are unable to determine the status of your payment.</span>
                </div>
                <p class="text-gray-700 mb-6">
                    Please check your invoice history or contact support for assistance.
                </p>
            @endif

            <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Login to View Your Invoices
            </a>
            @if (isset($invoice) && $invoice) {{-- If we successfully fetched the invoice details --}}
                <p class="mt-4 text-sm text-gray-500">
                    Your invoice #{{ $invoice->invoice_number }} status will be updated via webhook.
                </p>
            @endif
        </div>
    </div>
</x-guest-layout>