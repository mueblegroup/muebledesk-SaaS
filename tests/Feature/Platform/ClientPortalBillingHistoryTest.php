<?php

namespace Tests\Feature\Platform;

use App\Enums\UserRoleEnum;
use App\Models\Company;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPortalBillingHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_portal_lists_only_canonical_billing_records_for_the_selected_company(): void
    {
        $user = User::factory()->create([
            'role' => UserRoleEnum::Admin,
            'email_verified_at' => now(),
        ]);
        $company = $this->company('billing-company');
        $otherCompany = $this->company('other-company');
        $user->companies()->attach($company->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->payment($company, 'in_visible_123', 'Visible SaaS plan purchase');
        $this->payment($company, 'cs_checkout_confirmation', 'Temporary checkout confirmation');
        $this->payment($otherCompany, 'in_private_456', 'Other company billing record');

        $this->actingAs($user)
            ->get(route('client-portal.billing.history.index', $company))
            ->assertOk()
            ->assertSee('Visible SaaS plan purchase')
            ->assertDontSee('Temporary checkout confirmation')
            ->assertDontSee('Other company billing record')
            ->assertSee('View invoice')
            ->assertSee('Download receipt');
    }

    public function test_invoice_document_is_company_scoped(): void
    {
        $user = User::factory()->create([
            'role' => UserRoleEnum::Admin,
            'email_verified_at' => now(),
        ]);
        $company = $this->company('owner-company');
        $otherCompany = $this->company('private-company');
        $user->companies()->attach($company->id, ['role' => 'owner', 'joined_at' => now()]);

        $ownPayment = $this->payment($company, 'in_owner_123', 'Owner payment');
        $otherPayment = $this->payment($otherCompany, 'in_other_123', 'Private payment');

        $this->actingAs($user)
            ->get(route('client-portal.billing.history.invoice', [$company, $ownPayment]))
            ->assertRedirect('https://invoice.stripe.test/in_owner_123');

        $this->actingAs($user)
            ->get(route('client-portal.billing.history.invoice', [$company, $otherPayment]))
            ->assertNotFound();
    }

    public function test_paid_subscription_payment_receipt_can_be_downloaded(): void
    {
        $user = User::factory()->create([
            'role' => UserRoleEnum::Admin,
            'email_verified_at' => now(),
        ]);
        $company = $this->company('receipt-company');
        $user->companies()->attach($company->id, ['role' => 'owner', 'joined_at' => now()]);
        $payment = $this->payment($company, 'in_receipt_123', 'Receipt payment');

        $this->actingAs($user)
            ->get(route('client-portal.billing.history.receipt', [$company, $payment]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_api_guide_is_not_exposed_by_the_client_portal_layout(): void
    {
        $layout = file_get_contents(resource_path('views/components/client-portal-layout.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringNotContainsString('admin.api-guide', $layout);
        $this->assertStringNotContainsString("'API guide'", $layout);
    }

    private function company(string $slug): Company
    {
        return Company::create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
            'currency' => 'MYR',
            'timezone' => 'Asia/Kuala_Lumpur',
            'country_code' => 'MY',
        ]);
    }

    private function payment(Company $company, string $invoiceId, string $description): SubscriptionPayment
    {
        return SubscriptionPayment::create([
            'company_id' => $company->id,
            'provider' => 'stripe',
            'provider_invoice_id' => $invoiceId,
            'provider_payment_id' => 'pi_'.str()->lower(str()->random(12)),
            'provider_customer_id' => 'cus_'.str()->lower(str()->random(12)),
            'status' => 'paid',
            'amount' => 99.00,
            'currency' => 'MYR',
            'description' => $description,
            'paid_at' => now(),
            'metadata' => [
                'hosted_invoice_url' => 'https://invoice.stripe.test/'.$invoiceId,
                'invoice_pdf' => 'https://invoice.stripe.test/'.$invoiceId.'.pdf',
            ],
        ]);
    }
}
