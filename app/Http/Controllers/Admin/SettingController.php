<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    private array $definitions = [
        'company' => [
            'title' => 'Company & Invoice Identity',
            'description' => 'Business details used on invoices, receipts, and documents.',
            'fields' => [
                'company_name' => ['label' => 'Company Name', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => ''],
                'company_registration_number' => ['label' => 'Business Registration Number', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => ''],
                'company_email' => ['label' => 'Company Email', 'type' => 'email', 'rules' => 'nullable|email|max:255', 'default' => ''],
                'company_phone' => ['label' => 'Company Phone', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => ''],
                'company_address' => ['label' => 'Company Address', 'type' => 'textarea', 'rules' => 'nullable|string|max:2000', 'default' => ''],
                'company_website' => ['label' => 'Company Website', 'type' => 'url', 'rules' => 'nullable|url|max:255', 'default' => ''],
                'company_logo' => ['label' => 'Company Logo', 'type' => 'file', 'rules' => 'nullable|image|mimes:png,jpg,jpeg|max:2048|dimensions:min_width=200,min_height=60,max_width=1200,max_height=400', 'default' => '', 'help' => 'PNG or JPG, 200×60 to 1200×400 px, maximum 2 MB. A transparent PNG works best.'],
            ],
        ],
        'documents' => [
            'title' => 'Document Templates & Numbering',
            'description' => 'Choose default PDF designs and numbering sequence for invoices, quotations, and receipts.',
            'fields' => [
                'invoice_template' => ['label' => 'Invoice Template', 'type' => 'select', 'rules' => 'nullable|in:modern,classic,minimal', 'default' => 'modern', 'options' => ['modern' => 'Modern Indigo', 'classic' => 'Classic Corporate', 'minimal' => 'Minimal Clean']],
                'quotation_template' => ['label' => 'Quotation Template', 'type' => 'select', 'rules' => 'nullable|in:modern,classic,minimal', 'default' => 'modern', 'options' => ['modern' => 'Modern Indigo', 'classic' => 'Classic Corporate', 'minimal' => 'Minimal Clean']],
                'receipt_template' => ['label' => 'Payment Receipt Template', 'type' => 'select', 'rules' => 'nullable|in:modern,classic,minimal', 'default' => 'modern', 'options' => ['modern' => 'Modern Indigo', 'classic' => 'Classic Corporate', 'minimal' => 'Minimal Clean']],
                'invoice_prefix' => ['label' => 'Invoice Prefix', 'type' => 'text', 'rules' => 'nullable|string|max:50', 'default' => 'INV'],
                'invoice_number_format' => ['label' => 'Invoice Number Format', 'type' => 'select', 'rules' => 'nullable|in:sequential_yearly,sequential_monthly,sequential_global,timestamp,date', 'default' => 'sequential_yearly', 'options' => ['sequential_yearly' => 'Sequential yearly: INV-2026-00001', 'sequential_monthly' => 'Sequential monthly: INV-202607-00001', 'sequential_global' => 'Sequential global: INV-00001', 'timestamp' => 'Timestamp legacy', 'date' => 'Date legacy']],
                'quotation_prefix' => ['label' => 'Quotation Prefix', 'type' => 'text', 'rules' => 'nullable|string|max:50', 'default' => 'QT'],
                'quotation_number_format' => ['label' => 'Quotation Number Format', 'type' => 'select', 'rules' => 'nullable|in:sequential_yearly,sequential_monthly,sequential_global,timestamp,date', 'default' => 'sequential_yearly', 'options' => ['sequential_yearly' => 'Sequential yearly: QT-2026-00001', 'sequential_monthly' => 'Sequential monthly: QT-202607-00001', 'sequential_global' => 'Sequential global: QT-00001', 'timestamp' => 'Timestamp legacy', 'date' => 'Date legacy']],
                'receipt_prefix' => ['label' => 'Receipt Prefix', 'type' => 'text', 'rules' => 'nullable|string|max:50', 'default' => 'REC'],
                'receipt_number_format' => ['label' => 'Receipt Number Format', 'type' => 'select', 'rules' => 'nullable|in:sequential_yearly,sequential_monthly,sequential_global,timestamp,date', 'default' => 'sequential_yearly', 'options' => ['sequential_yearly' => 'Sequential yearly: REC-2026-00001', 'sequential_monthly' => 'Sequential monthly: REC-202607-00001', 'sequential_global' => 'Sequential global: REC-00001', 'timestamp' => 'Timestamp legacy', 'date' => 'Date legacy']],
            ],
        ],
        'invoice' => [
            'title' => 'Invoice Defaults',
            'description' => 'Defaults used when creating invoices and quotations.',
            'fields' => [
                'currency' => ['label' => 'Currency', 'type' => 'text', 'rules' => 'nullable|string|max:10', 'default' => 'MYR'],
                'default_invoice_due_days' => ['label' => 'Default Invoice Due Days', 'type' => 'number', 'rules' => 'nullable|integer|min:0|max:365', 'default' => '14'],
                'invoice_footer_note' => ['label' => 'Invoice Footer Note', 'type' => 'textarea', 'rules' => 'nullable|string|max:3000', 'default' => ''],
                'payment_terms' => ['label' => 'Payment Terms', 'type' => 'textarea', 'rules' => 'nullable|string|max:3000', 'default' => ''],
            ],
        ],
        'myinvois' => [
            'title' => 'MyInvois e-Invoice Supplier Profile',
            'description' => 'Supplier identity sent to HASiL. API credentials and environment controls remain securely in the server .env file.',
            'fields' => [
                'myinvois_supplier_tin' => ['label' => 'Supplier TIN', 'type' => 'text', 'rules' => 'nullable|string|max:20', 'default' => ''],
                'myinvois_supplier_registration_type' => ['label' => 'Registration Type', 'type' => 'select', 'rules' => 'nullable|in:NRIC,BRN,PASSPORT,ARMY', 'default' => 'BRN', 'options' => ['NRIC' => 'NRIC', 'BRN' => 'Business Registration Number', 'PASSPORT' => 'Passport', 'ARMY' => 'Army']],
                'myinvois_supplier_registration_number' => ['label' => 'Registration / ID Number', 'type' => 'text', 'rules' => 'nullable|string|max:50', 'default' => ''],
                'myinvois_supplier_name' => ['label' => 'Registered Supplier Name', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => ''],
                'myinvois_supplier_msic_code' => ['label' => 'MSIC Code', 'type' => 'text', 'rules' => 'nullable|digits:5', 'default' => '62010', 'help' => 'Use the five-digit activity code matching the business tax profile.'],
                'myinvois_supplier_business_activity' => ['label' => 'Business Activity', 'type' => 'text', 'rules' => 'nullable|string|max:300', 'default' => 'Computer programming activities'],
                'myinvois_supplier_sst_number' => ['label' => 'SST Registration Number', 'type' => 'text', 'rules' => 'nullable|string|max:50', 'default' => 'NA'],
                'myinvois_supplier_ttx_number' => ['label' => 'Tourism Tax Number', 'type' => 'text', 'rules' => 'nullable|string|max:50', 'default' => 'NA'],
                'myinvois_supplier_email' => ['label' => 'Supplier Email', 'type' => 'email', 'rules' => 'nullable|email|max:255', 'default' => ''],
                'myinvois_supplier_phone' => ['label' => 'Supplier Phone', 'type' => 'text', 'rules' => 'nullable|string|max:30', 'default' => ''],
                'myinvois_supplier_address_line_1' => ['label' => 'Address Line 1', 'type' => 'text', 'rules' => 'nullable|string|max:150', 'default' => ''],
                'myinvois_supplier_address_line_2' => ['label' => 'Address Line 2', 'type' => 'text', 'rules' => 'nullable|string|max:150', 'default' => ''],
                'myinvois_supplier_city' => ['label' => 'City', 'type' => 'text', 'rules' => 'nullable|string|max:100', 'default' => ''],
                'myinvois_supplier_state_code' => ['label' => 'MyInvois State Code', 'type' => 'text', 'rules' => 'nullable|string|max:3', 'default' => '', 'help' => 'Example: 10 for Selangor, 14 for Kuala Lumpur.'],
                'myinvois_supplier_postcode' => ['label' => 'Postcode', 'type' => 'text', 'rules' => 'nullable|string|max:10', 'default' => ''],
                'myinvois_supplier_country_code' => ['label' => 'Country Code', 'type' => 'text', 'rules' => 'nullable|string|size:3', 'default' => 'MYS'],
            ],
        ],
        'tax' => [
            'title' => 'Tax Settings',
            'description' => 'Optional tax/SST information for documents and e-Invoice integration.',
            'fields' => [
                'tax_enabled' => ['label' => 'Enable Tax', 'type' => 'select', 'rules' => 'nullable|in:0,1', 'default' => '0', 'options' => ['0' => 'No', '1' => 'Yes']],
                'tax_name' => ['label' => 'Tax Name', 'type' => 'text', 'rules' => 'nullable|string|max:50', 'default' => 'SST'],
                'default_tax_type' => ['label' => 'Default Tax Type', 'type' => 'select', 'rules' => 'nullable|in:none,sst,service_tax,sales_tax,tourism_tax,exempt,zero_rated,other', 'default' => 'none', 'options' => ['none' => 'No Tax', 'sst' => 'SST', 'service_tax' => 'Service Tax', 'sales_tax' => 'Sales Tax', 'tourism_tax' => 'Tourism Tax', 'exempt' => 'Tax Exempt', 'zero_rated' => 'Zero Rated', 'other' => 'Other Tax']],
                'tax_rate' => ['label' => 'Default Tax Rate (%)', 'type' => 'number', 'rules' => 'nullable|numeric|min:0|max:100', 'default' => '0'],
                'tax_registration_number' => ['label' => 'Tax Registration Number', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => ''],
            ],
        ],
        'payments' => [
            'title' => 'Payment Gateway Selection',
            'description' => 'Choose which gateway is used for invoice payment links across manual invoices, recurring invoices, PDFs, and the customer portal.',
            'fields' => [
                'payment_gateway' => ['label' => 'Default Payment Gateway', 'type' => 'select', 'rules' => 'nullable|in:hitpay,stripe', 'default' => 'hitpay', 'options' => ['hitpay' => 'HitPay', 'stripe' => 'Stripe Checkout']],
            ],
        ],
        'hitpay' => [
            'title' => 'HitPay Payment Gateway',
            'description' => 'API keys and webhook details used for HitPay payment links and callbacks.',
            'fields' => [
                'hitpay_enabled' => ['label' => 'Enable HitPay', 'type' => 'select', 'rules' => 'nullable|in:0,1', 'default' => '1', 'options' => ['0' => 'No', '1' => 'Yes']],
                'hitpay_mode' => ['label' => 'HitPay Mode', 'type' => 'select', 'rules' => 'nullable|in:sandbox,production', 'default' => 'sandbox', 'options' => ['sandbox' => 'Sandbox', 'production' => 'Production']],
                'hitpay_api_key' => ['label' => 'HitPay API Key', 'type' => 'password_text', 'rules' => 'nullable|string|max:1000', 'default' => ''],
                'hitpay_salt' => ['label' => 'HitPay Salt Key / Payment Request Salt', 'type' => 'password_text', 'rules' => 'nullable|string|max:1000', 'default' => ''],
                'hitpay_webhook_url' => ['label' => 'HitPay Webhook URL', 'type' => 'url', 'rules' => 'nullable|url|max:255', 'default' => ''],
                'hitpay_webhook_salt' => ['label' => 'HitPay Webhook Salt Key', 'type' => 'password_text', 'rules' => 'nullable|string|max:1000', 'default' => ''],
            ],
        ],
        'stripe' => [
            'title' => 'Stripe Payment Gateway',
            'description' => 'Stripe Checkout settings used for invoice payment links and webhook callbacks.',
            'fields' => [
                'stripe_enabled' => ['label' => 'Enable Stripe', 'type' => 'select', 'rules' => 'nullable|in:0,1', 'default' => '0', 'options' => ['0' => 'No', '1' => 'Yes']],
                'stripe_publishable_key' => ['label' => 'Stripe Publishable Key', 'type' => 'text', 'rules' => 'nullable|string|max:1000', 'default' => '', 'help' => 'Usually starts with pk_test_ or pk_live_. Not required for server-created Checkout links, but useful for future Stripe Elements.'],
                'stripe_secret_key' => ['label' => 'Stripe Secret Key', 'type' => 'password_text', 'rules' => 'nullable|string|max:1000', 'default' => '', 'help' => 'Use sk_test_... for testing or sk_live_... for production.'],
                'stripe_webhook_url' => ['label' => 'Stripe Webhook URL', 'type' => 'url', 'rules' => 'nullable|url|max:255', 'default' => ''],
                'stripe_webhook_secret' => ['label' => 'Stripe Webhook Signing Secret', 'type' => 'password_text', 'rules' => 'nullable|string|max:1000', 'default' => '', 'help' => 'Starts with whsec_... from the Stripe webhook endpoint.'],
            ],
        ],
        'mail' => [
            'title' => 'Email Defaults',
            'description' => 'Sender information for invoice and payment emails.',
            'fields' => [
                'mail_from_name' => ['label' => 'Mail From Name', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => ''],
                'mail_from_address' => ['label' => 'Mail From Address', 'type' => 'email', 'rules' => 'nullable|email|max:255', 'default' => ''],
                'invoice_email_subject' => ['label' => 'Invoice Email Subject', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => 'Your invoice is ready'],
                'invoice_email_message' => ['label' => 'Invoice Email Message', 'type' => 'textarea', 'rules' => 'nullable|string|max:3000', 'default' => ''],
            ],
        ],
        'system' => [
            'title' => 'System Behaviour',
            'description' => 'General app-level switches and defaults.',
            'fields' => [
                'app_timezone' => ['label' => 'Timezone', 'type' => 'text', 'rules' => 'nullable|string|max:100', 'default' => 'Asia/Kuala_Lumpur'],
                'records_per_page' => ['label' => 'Records Per Page', 'type' => 'number', 'rules' => 'nullable|integer|min:5|max:200', 'default' => '10'],
                'allow_customer_download_invoice' => ['label' => 'Allow Customer Invoice Download', 'type' => 'select', 'rules' => 'nullable|in:0,1', 'default' => '1', 'options' => ['0' => 'No', '1' => 'Yes']],
                'auto_generate_payment_link' => ['label' => 'Auto Generate Payment Link', 'type' => 'select', 'rules' => 'nullable|in:0,1', 'default' => '1', 'options' => ['0' => 'No', '1' => 'Yes']],
            ],
        ],
    ];

    public function index()
    {
        $settings = [];

        foreach ($this->definitions as $section) {
            foreach ($section['fields'] as $key => $field) {
                $settings[$key] = Setting::get($key, $field['default'] ?? '');
            }
        }

        if (empty($settings['hitpay_webhook_url'])) {
            $settings['hitpay_webhook_url'] = route('hitpay.webhook');
        }

        if (empty($settings['stripe_webhook_url'])) {
            $settings['stripe_webhook_url'] = route('stripe.webhook');
        }

        return view('admin.setting.index', [
            'sections' => $this->definitions,
            'settings' => $settings,
            'webhookUrl' => route('hitpay.webhook'),
            'hitpayWebhookUrl' => route('hitpay.webhook'),
            'stripeWebhookUrl' => route('stripe.webhook'),
        ]);
    }

    public function update(Request $request, ActivityLogger $activityLogger)
    {
        $rules = [];

        foreach ($this->definitions as $section) {
            foreach ($section['fields'] as $key => $field) {
                $rules[$key] = $field['rules'] ?? 'nullable|string|max:255';
            }
        }

        $validated = $request->validate($rules);

        try {
            if ($request->hasFile('company_logo')) {
                $oldLogo = Setting::get('company_logo');
                $validated['company_logo'] = $request->file('company_logo')->store('company', 'public');
                if ($oldLogo) {
                    Storage::disk('public')->delete($oldLogo);
                }
            } else {
                unset($validated['company_logo']);
            }

            foreach ($this->definitions as $section) {
                foreach ($section['fields'] as $key => $field) {
                    if ($key === 'company_logo' && ! array_key_exists($key, $validated)) {
                        continue;
                    }

                    $oldValue = Setting::get($key, $field['default'] ?? '');
                    $newValue = $validated[$key] ?? '';

                    if (($field['type'] ?? null) === 'password_text' && $newValue === '' && $oldValue !== '') {
                        continue;
                    }

                    Setting::set($key, $newValue);

                    if ((string) $oldValue !== (string) $newValue) {
                        $activityLogger->log('settings.changed', 'Setting updated: '.$key, null, ['key' => $key, 'value' => $oldValue], ['key' => $key, 'value' => $newValue]);
                    }
                }
            }

            return redirect()
                ->route('admin.setting.index')
                ->with('success', 'Settings updated successfully.');
        } catch (\Throwable $e) {
            Log::error('Error updating settings: '.$e->getMessage(), ['exception' => $e]);

            return back()
                ->with('error', 'Failed to update settings. Please check the logs and try again.')
                ->withInput();
        }
    }
}
