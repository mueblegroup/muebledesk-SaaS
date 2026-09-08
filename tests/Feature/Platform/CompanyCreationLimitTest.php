<?php

namespace Tests\Feature\Platform;

use App\Enums\UserRoleEnum;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCreationLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_company_is_allowed_before_any_subscription_exists(): void
    {
        $user = $this->user();

        $this->assertSame(1, $user->companyCreationLimit());
        $this->assertTrue($user->canCreateCompany());
    }

    public function test_active_plan_company_limit_is_enforced_across_owned_companies(): void
    {
        $user = $this->user();
        $first = $this->company('first-company');
        $second = $this->company('second-company');
        $user->companies()->attach($first->id, ['role' => 'owner', 'joined_at' => now()]);
        $user->companies()->attach($second->id, ['role' => 'owner', 'joined_at' => now()]);

        $plan = $this->plan(2);
        CompanySubscription::create([
            'company_id' => $first->id,
            'platform_subscription_plan_id' => $plan->id,
            'status' => 'active',
            'is_enabled' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertSame(2, $user->companyCreationLimit());
        $this->assertFalse($user->canCreateCompany());
    }

    public function test_highest_active_company_allowance_wins_and_unlimited_plan_is_unlimited(): void
    {
        $user = $this->user();
        $first = $this->company('first-company');
        $second = $this->company('second-company');
        $user->companies()->attach($first->id, ['role' => 'owner', 'joined_at' => now()]);
        $user->companies()->attach($second->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->subscribe($first, $this->plan(2));
        $this->subscribe($second, $this->plan(5, 'Business'));

        $this->assertSame(5, $user->companyCreationLimit());
        $this->assertTrue($user->canCreateCompany());

        $this->subscribe($second, $this->plan(null, 'Unlimited'));
        $this->assertNull($user->companyCreationLimit());
        $this->assertTrue($user->canCreateCompany());
    }

    public function test_portal_switch_updates_current_company_without_entering_tenant_workspace(): void
    {
        $user = $this->user();
        $first = $this->company('first-company');
        $second = $this->company('second-company');
        $user->companies()->attach($first->id, ['role' => 'owner', 'joined_at' => now()]);
        $user->companies()->attach($second->id, ['role' => 'admin', 'joined_at' => now()]);
        $user->forceFill(['current_company_id' => $first->id])->save();

        $response = $this->actingAs($user)->post(route('companies.switch', $second), [
            'destination' => 'portal',
        ]);

        $response->assertRedirect(route('client-portal.dashboard'));
        $this->assertSame($second->id, $user->fresh()->current_company_id);
    }

    private function user(): User
    {
        return User::factory()->create([
            'role' => UserRoleEnum::Admin,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
        ]);
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

    private function plan(?int $companyLimit, string $name = 'Plan'): PlatformSubscriptionPlan
    {
        static $rank = 0;
        $rank++;

        return PlatformSubscriptionPlan::create([
            'name' => $name.' '.$rank,
            'slug' => str($name.' '.$rank)->slug(),
            'price' => 50,
            'currency' => 'MYR',
            'duration_value' => 1,
            'duration_unit' => 'months',
            'company_limit' => $companyLimit,
            'is_active' => true,
            'billing_rank' => $rank,
        ]);
    }

    private function subscribe(Company $company, PlatformSubscriptionPlan $plan): void
    {
        CompanySubscription::updateOrCreate(
            ['company_id' => $company->id],
            [
                'platform_subscription_plan_id' => $plan->id,
                'status' => 'active',
                'is_enabled' => true,
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addMonth(),
            ]
        );
    }
}
