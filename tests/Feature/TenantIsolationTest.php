<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_scope_hides_other_company_records(): void
    {
        [$companyA, $companyB] = $this->companies();
        $this->bindCompany($companyA);

        $clientA = Client::create($this->clientData('Company A Client', 'client-a@example.test'));
        $clientB = Client::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            ...$this->clientData('Company B Client', 'client-b@example.test'),
        ]);

        $this->assertSame($companyA->id, $clientA->company_id);
        $this->assertTrue(Client::query()->whereKey($clientA)->exists());
        $this->assertFalse(Client::query()->whereKey($clientB)->exists());
        $this->assertSame(1, Client::query()->count());
    }

    public function test_child_records_inherit_company_from_parent(): void
    {
        [$company] = $this->companies();
        $this->bindCompany($company);
        $employee = User::factory()->create();

        $client = Client::create($this->clientData('Client', 'child-test@example.test'));
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'invoice_number' => 'INV-TEST-001',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => 'draft',
            'sub_total' => 100,
            'total_amount' => 100,
            'amount_paid' => 0,
        ]);

        $item = $invoice->items()->create([
            'item_name' => 'Tenant-safe item',
            'quantity' => 1,
            'price' => 100,
            'total' => 100,
        ]);

        $this->assertSame($company->id, $invoice->company_id);
        $this->assertSame($company->id, $item->company_id);
    }

    public function test_settings_and_their_cache_are_isolated_by_company(): void
    {
        [$companyA, $companyB] = $this->companies();

        $this->bindCompany($companyA);
        Setting::set('currency', 'MYR');

        $this->bindCompany($companyB);
        Setting::set('currency', 'SGD');
        $this->assertSame('SGD', Setting::get('currency'));

        $this->bindCompany($companyA);
        $this->assertSame('MYR', Setting::get('currency'));
    }

    public function test_public_payment_confirmation_does_not_resolve_predictable_invoice_ids(): void
    {
        [$company] = $this->companies();
        $this->bindCompany($company);
        $employee = User::factory()->create();

        $client = Client::create($this->clientData('Private Client', 'private-client@example.test'));
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'employee_id' => $employee->id,
            'invoice_number' => 'PRIVATE-INVOICE-999',
            'date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => 'pending',
            'sub_total' => 10,
            'total_amount' => 10,
            'amount_paid' => 0,
        ]);

        $this->forgetCompany();

        $this->get('/payment/confirmation?status=completed&reference=INV-'.$invoice->id)
            ->assertOk()
            ->assertDontSee('PRIVATE-INVOICE-999');
    }

    protected function clientData(string $name, string $email): array
    {
        return [
            'name' => $name,
            'client_type' => 'business',
            'email' => $email,
        ];
    }

    protected function companies(): array
    {
        return [
            Company::create([
                'name' => 'Company A', 'slug' => 'company-a', 'currency' => 'MYR',
                'timezone' => 'Asia/Kuala_Lumpur', 'country_code' => 'MY',
            ]),
            Company::create([
                'name' => 'Company B', 'slug' => 'company-b', 'currency' => 'SGD',
                'timezone' => 'Asia/Singapore', 'country_code' => 'SG',
            ]),
        ];
    }

    protected function bindCompany(Company $company): void
    {
        app()->instance(Company::class, $company);
        app()->instance('currentCompany', $company);
    }

    protected function forgetCompany(): void
    {
        app()->forgetInstance('currentCompany');
        app()->forgetInstance(Company::class);
    }

    protected function tearDown(): void
    {
        $this->forgetCompany();
        parent::tearDown();
    }
}
