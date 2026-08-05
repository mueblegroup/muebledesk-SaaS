<?php

namespace Tests\Feature\Tenancy;

use App\Enums\UserRoleEnum;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_switch_into_an_unrelated_company(): void
    {
        $user = User::factory()->create([
            'role' => UserRoleEnum::Admin,
            'email_verified_at' => now(),
        ]);

        $ownedCompany = $this->company('owned-company');
        $unrelatedCompany = $this->company('unrelated-company');

        $user->companies()->attach($ownedCompany->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('companies.switch', $unrelatedCompany))
            ->assertForbidden();

        $this->assertNull($user->fresh()->current_company_id);
    }

    public function test_user_can_switch_only_into_a_company_they_belong_to(): void
    {
        $user = User::factory()->create([
            'role' => UserRoleEnum::Admin,
            'email_verified_at' => now(),
        ]);

        $company = $this->company('authorised-company');
        $user->companies()->attach($company->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('companies.switch', $company))
            ->assertRedirect();

        $this->assertSame($company->id, $user->fresh()->current_company_id);
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
