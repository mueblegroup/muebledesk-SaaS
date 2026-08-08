<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MyInvois\MyInvoisClient;
use App\Services\MyInvois\SupplierProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class MyInvoisSettingController extends Controller
{
    public function index(SupplierProfile $supplierProfile): View
    {
        $environment = (string) Setting::get('myinvois_environment', 'sandbox');
        $enabled = filter_var(Setting::get('myinvois_enabled', '0'), FILTER_VALIDATE_BOOL);
        $missing = $supplierProfile->missingRequiredFields();
        $credentialKey = "myinvois_{$environment}_client_id";
        $secretKey = "myinvois_{$environment}_client_secret";
        $credentialsReady = filled(Setting::get($credentialKey)) && filled(Setting::get($secretKey));

        return view('admin.einvoice-settings.index', [
            'enabled' => $enabled,
            'environment' => $environment,
            'profile' => $supplierProfile->get(),
            'missing' => $missing,
            'credentialsReady' => $credentialsReady,
            'sandboxClientId' => Setting::get('myinvois_sandbox_client_id', ''),
            'productionClientId' => Setting::get('myinvois_production_client_id', ''),
            'lastTestedAt' => Setting::get('myinvois_last_tested_at', ''),
            'lastTestStatus' => Setting::get('myinvois_last_test_status', ''),
            'lastTestMessage' => Setting::get('myinvois_last_test_message', ''),
        ]);
    }

    public function update(Request $request, MyInvoisClient $client): RedirectResponse
    {
        $validated = $request->validate([
            'myinvois_enabled' => ['nullable', 'boolean'],
            'myinvois_environment' => ['required', 'in:sandbox,production'],
            'myinvois_sandbox_client_id' => ['nullable', 'string', 'max:1000'],
            'myinvois_sandbox_client_secret' => ['nullable', 'string', 'max:2000'],
            'myinvois_production_client_id' => ['nullable', 'string', 'max:1000'],
            'myinvois_production_client_secret' => ['nullable', 'string', 'max:2000'],
            'myinvois_supplier_tin' => ['required', 'string', 'max:20'],
            'myinvois_supplier_registration_type' => ['required', 'in:NRIC,BRN,PASSPORT,ARMY'],
            'myinvois_supplier_registration_number' => ['required', 'string', 'max:50'],
            'myinvois_supplier_name' => ['required', 'string', 'max:255'],
            'myinvois_supplier_msic_code' => ['required', 'digits:5'],
            'myinvois_supplier_business_activity' => ['required', 'string', 'max:300'],
            'myinvois_supplier_sst_number' => ['nullable', 'string', 'max:50'],
            'myinvois_supplier_ttx_number' => ['nullable', 'string', 'max:50'],
            'myinvois_supplier_email' => ['nullable', 'email', 'max:255'],
            'myinvois_supplier_phone' => ['required', 'string', 'max:30'],
            'myinvois_supplier_address_line_1' => ['required', 'string', 'max:150'],
            'myinvois_supplier_address_line_2' => ['nullable', 'string', 'max:150'],
            'myinvois_supplier_city' => ['required', 'string', 'max:100'],
            'myinvois_supplier_state_code' => ['required', 'string', 'max:3'],
            'myinvois_supplier_postcode' => ['required', 'string', 'max:10'],
            'myinvois_supplier_country_code' => ['required', 'string', 'size:3'],
        ]);

        $validated['myinvois_enabled'] = $request->boolean('myinvois_enabled') ? '1' : '0';

        foreach ($validated as $key => $value) {
            if (str_ends_with($key, '_client_secret') && blank($value) && filled(Setting::get($key))) {
                continue;
            }
            Setting::set($key, is_string($value) ? trim($value) : $value);
        }

        $client->forgetCachedToken();
        Setting::set('myinvois_last_test_status', '');
        Setting::set('myinvois_last_test_message', 'Configuration changed. Run Test Connection again.');

        return back()->with('success', 'e-Invoice configuration saved. Test the connection before submitting live invoices.');
    }

    public function testConnection(MyInvoisClient $client, SupplierProfile $supplierProfile): RedirectResponse
    {
        if ($missing = $supplierProfile->missingRequiredFields()) {
            return back()->with('error', 'Complete the supplier profile first: '.implode(', ', $missing).'.');
        }

        try {
            $result = $client->testConnection();
            $profile = $supplierProfile->get();
            $tinValid = $client->validateTin(
                (string) $profile['tin'],
                (string) $profile['registration_type'],
                (string) $profile['registration_number']
            );

            if (! $tinValid) {
                throw new \RuntimeException('Authentication succeeded, but the supplier TIN does not match the configured registration identity.');
            }

            Setting::set('myinvois_last_tested_at', now()->toDateTimeString());
            Setting::set('myinvois_last_test_status', 'connected');
            Setting::set('myinvois_last_test_message', 'Authentication and supplier TIN validation succeeded in '.strtoupper($result['environment']).'.');

            return back()->with('success', 'MyInvois connection successful. Supplier TIN was validated.');
        } catch (Throwable $e) {
            Setting::set('myinvois_last_tested_at', now()->toDateTimeString());
            Setting::set('myinvois_last_test_status', 'failed');
            Setting::set('myinvois_last_test_message', $e->getMessage());

            return back()->with('error', 'MyInvois connection failed: '.$e->getMessage());
        }
    }
}
