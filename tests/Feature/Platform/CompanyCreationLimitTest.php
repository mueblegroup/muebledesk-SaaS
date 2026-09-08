<?php

namespace Tests\Feature\Platform;

use App\Enums\UserRoleEnum;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCreationLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_own_multiple_companies_without_plan_based_account_limit(): void
    {
        $user = $this->user();
        $first = $this->company('first-company');
        $second = $this->company('second-company');
        $third = $this->company('third-company');

        $user->companies()->attach($first->id, ['role' => 'owner', 'joined_at' => now()]);
        $user->companies()->attach($second->id, ['role' => 'owner', 'joined_at' => now()]);
        $user->companies()->attach($third->id, ['role' => 'owner', 'joined_at' => now()]);

        $this->assertSame(3, $user->companies()->wherePivot('role', 'owner')->count());
        $this->assertTrue(route('companies.create') !== '');
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
}
