<?php

namespace App\Services\MyInvois;

use App\Models\Invoice;
use Illuminate\Support\Str;

class UblInvoiceBuilder
{
    public function __construct(private readonly SupplierProfile $supplierProfile)
    {
    }

    public function build(Invoice $invoice): array
    {
        $invoice->loadMissing('client', 'items');
        $client = $invoice->client;
        $supplier = $this->supplierProfile->get();
        $currency = strtoupper((string) config('myinvois.currency', 'MYR'));
        $version = (string) config('myinvois.document_version', '1.0');
        $issuedAt = now('UTC');
        $issueDate = $issuedAt->format('Y-m-d');
        $issueTime = $issuedAt->format('H:i:s\Z');
        $taxType = $this->taxTypeCode($invoice->tax_type);

        $lines = [];
        foreach ($invoice->items as $index => $item) {
            $lineExtension = round((float) $item->total, 2);
            $lineTax = $this->allocatedLineTax($invoice, $lineExtension);
            $lines[] = [
                'ID' => [['_'=> (string) ($index + 1)]],
                'InvoicedQuantity' => [['_'=> (float) $item->quantity, 'unitCode' => 'C62']],
                'LineExtensionAmount' => [['_'=> $lineExtension, 'currencyID' => $currency]],
                'TaxTotal' => [[
                    'TaxAmount' => [['_'=> $lineTax, 'currencyID' => $currency]],
                    'TaxSubtotal' => [[
                        'TaxableAmount' => [['_'=> $lineExtension, 'currencyID' => $currency]],
                        'TaxAmount' => [['_'=> $lineTax, 'currencyID' => $currency]],
                        'TaxCategory' => [[
                            'ID' => [['_'=> $taxType]],
                            'Percent' => [['_'=> (float) $invoice->tax_rate]],
                            'TaxScheme' => [['ID' => [['_'=> 'OTH', 'schemeID' => 'UN/ECE 5153', 'schemeAgencyID' => '6']]]],
                        ]],
                    ]],
                ]],
                'Item' => [[
                    'CommodityClassification' => [['ItemClassificationCode' => [['_'=> '022', 'listID' => 'CLASS']]]],
                    'Description' => [['_'=> Str::limit(strip_tags((string) ($item->description ?: $item->item_name)), 300)]],
                ]],
                'Price' => [['PriceAmount' => [['_'=> round((float) $item->price, 2), 'currencyID' => $currency]]]],
                'ItemPriceExtension' => [['Amount' => [['_'=> $lineExtension, 'currencyID' => $currency]]]],
            ];
        }

        $document = [
            '_D' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            '_A' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
            '_B' => 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
            'Invoice' => [[
                'ID' => [['_'=> (string) $invoice->invoice_number]],
                'IssueDate' => [['_'=> $issueDate]],
                'IssueTime' => [['_'=> $issueTime]],
                'InvoiceTypeCode' => [['_'=> '01', 'listVersionID' => $version]],
                'DocumentCurrencyCode' => [['_'=> $currency]],
                'TaxCurrencyCode' => [['_'=> $currency]],
                'AccountingSupplierParty' => [[
                    'AdditionalAccountID' => [['_'=> 'NA', 'schemeAgencyName' => 'CertEX']],
                    'Party' => [$this->party(
                        $supplier['tin'], $supplier['registration_type'], $supplier['registration_number'],
                        $supplier['sst_number'] ?? 'NA', $supplier['ttx_number'] ?? 'NA', $supplier['name'],
                        $supplier['phone'], $supplier['email'] ?? null, $supplier['address_line_1'],
                        $supplier['address_line_2'] ?? null, $supplier['city'], $supplier['postcode'],
                        $supplier['state_code'], $supplier['country_code'], $supplier['msic_code'],
                        $supplier['business_activity']
                    )],
                ]],
                'AccountingCustomerParty' => [[
                    'Party' => [$this->party(
                        $client->tin_number, $client->id_type, $client->id_number,
                        $client->sst_registration_number ?: 'NA', 'NA', $client->name,
                        $client->phone, $client->billing_email ?: $client->email,
                        $client->address_line_1, $client->address_line_2, $client->city,
                        $client->postcode, $client->state, $client->country_code
                    )],
                ]],
                'TaxTotal' => [[
                    'TaxAmount' => [['_'=> round((float) $invoice->tax_amount, 2), 'currencyID' => $currency]],
                    'TaxSubtotal' => [[
                        'TaxableAmount' => [['_'=> round((float) $invoice->sub_total - (float) $invoice->discount_amount, 2), 'currencyID' => $currency]],
                        'TaxAmount' => [['_'=> round((float) $invoice->tax_amount, 2), 'currencyID' => $currency]],
                        'TaxCategory' => [[
                            'ID' => [['_'=> $taxType]],
                            'Percent' => [['_'=> (float) $invoice->tax_rate]],
                            'TaxScheme' => [['ID' => [['_'=> 'OTH', 'schemeID' => 'UN/ECE 5153', 'schemeAgencyID' => '6']]]],
                        ]],
                    ]],
                ]],
                'LegalMonetaryTotal' => [[
                    'LineExtensionAmount' => [['_'=> round((float) $invoice->sub_total, 2), 'currencyID' => $currency]],
                    'TaxExclusiveAmount' => [['_'=> round((float) $invoice->total_amount - (float) $invoice->tax_amount, 2), 'currencyID' => $currency]],
                    'TaxInclusiveAmount' => [['_'=> round((float) $invoice->total_amount, 2), 'currencyID' => $currency]],
                    'AllowanceTotalAmount' => [['_'=> round((float) $invoice->discount_amount, 2), 'currencyID' => $currency]],
                    'PayableAmount' => [['_'=> round((float) $invoice->total_amount, 2), 'currencyID' => $currency]],
                ]],
                'InvoiceLine' => $lines,
            ]],
        ];

        $json = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $json);

        return [
            'document' => $document,
            'json' => $json,
            'hash' => $hash,
            'submission_document' => [
                'format' => 'JSON',
                'document' => base64_encode($json),
                'documentHash' => $hash,
                'codeNumber' => (string) $invoice->invoice_number,
            ],
        ];
    }

    private function party(string $tin, string $idType, string $idNumber, string $sst, string $ttx, string $name, string $phone, ?string $email, string $line1, ?string $line2, string $city, string $postcode, string $state, string $country, ?string $msic = null, ?string $activity = null): array
    {
        $country = CountryCode::alpha3($country);
        $stateCode = StateCode::normalize($state, $country);
        if ($stateCode === null) {
            throw new \InvalidArgumentException('Invalid MyInvois state value: '.$state);
        }

        $party = [
            'IndustryClassificationCode' => $msic ? [['_'=> $msic, 'name' => $activity ?: 'Business activity']] : [],
            'PartyIdentification' => [
                ['ID' => [['_'=> $tin, 'schemeID' => 'TIN']]],
                ['ID' => [['_'=> $idNumber, 'schemeID' => strtoupper($idType)]]],
                ['ID' => [['_'=> $sst ?: 'NA', 'schemeID' => 'SST']]],
                ['ID' => [['_'=> $ttx ?: 'NA', 'schemeID' => 'TTX']]],
            ],
            'PostalAddress' => [[
                'CityName' => [['_'=> $city]],
                'PostalZone' => [['_'=> $postcode]],
                'CountrySubentityCode' => [['_'=> $stateCode]],
                'AddressLine' => array_values(array_filter([
                    ['Line' => [['_'=> $line1]]],
                    $line2 ? ['Line' => [['_'=> $line2]]] : null,
                ])),
                'Country' => [['IdentificationCode' => [['_'=> $country, 'listID' => 'ISO3166-1', 'listAgencyID' => '6']]]],
            ]],
            'PartyLegalEntity' => [['RegistrationName' => [['_'=> $name]]]],
            'Contact' => [['Telephone' => [['_'=> $phone]], 'ElectronicMail' => [['_'=> $email ?: 'NA']]]],
        ];

        if (! $msic) unset($party['IndustryClassificationCode']);
        return $party;
    }

    private function taxTypeCode(?string $type): string
    {
        return match ($type) {
            'sales_tax', 'sst' => '01',
            'service_tax' => '02',
            'tourism_tax' => '04',
            'exempt' => 'E',
            default => '06',
        };
    }

    private function allocatedLineTax(Invoice $invoice, float $lineExtension): float
    {
        if ((float) $invoice->sub_total <= 0 || (float) $invoice->tax_amount <= 0) return 0.0;
        return round(((float) $invoice->tax_amount * $lineExtension) / (float) $invoice->sub_total, 2);
    }
}
