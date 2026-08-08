<?php

namespace App\Services\MyInvois;

use App\Models\Setting;

class SupplierProfile
{
    public function get(): array
    {
        $fallback = app()->bound('currentCompany') ? [] : (array) config('myinvois.supplier', []);

        $map = [
            'tin' => 'myinvois_supplier_tin',
            'registration_type' => 'myinvois_supplier_registration_type',
            'registration_number' => 'myinvois_supplier_registration_number',
            'sst_number' => 'myinvois_supplier_sst_number',
            'ttx_number' => 'myinvois_supplier_ttx_number',
            'msic_code' => 'myinvois_supplier_msic_code',
            'business_activity' => 'myinvois_supplier_business_activity',
            'name' => 'myinvois_supplier_name',
            'email' => 'myinvois_supplier_email',
            'phone' => 'myinvois_supplier_phone',
            'address_line_1' => 'myinvois_supplier_address_line_1',
            'address_line_2' => 'myinvois_supplier_address_line_2',
            'city' => 'myinvois_supplier_city',
            'state_code' => 'myinvois_supplier_state_code',
            'postcode' => 'myinvois_supplier_postcode',
            'country_code' => 'myinvois_supplier_country_code',
        ];

        $profile = [];
        foreach ($map as $key => $settingKey) {
            $profile[$key] = Setting::get($settingKey, $fallback[$key] ?? null);
        }

        $profile['sst_number'] = $profile['sst_number'] ?: 'NA';
        $profile['ttx_number'] = $profile['ttx_number'] ?: 'NA';
        $profile['country_code'] = $profile['country_code'] ?: 'MYS';

        return $profile;
    }

    public function missingRequiredFields(): array
    {
        $profile = $this->get();
        $required = ['tin', 'registration_type', 'registration_number', 'msic_code', 'business_activity', 'name', 'phone', 'address_line_1', 'city', 'state_code', 'postcode', 'country_code'];

        return collect($required)
            ->filter(fn (string $field) => blank($profile[$field] ?? null))
            ->values()
            ->all();
    }
}
