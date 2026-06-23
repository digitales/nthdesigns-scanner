<?php

namespace Tests\Feature;

use App\Jobs\CheckCompaniesHouseJob;
use App\Models\Prospect;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RegisteredCompanyTest extends TestCase
{
    use RefreshDatabase;

    private function prospectFor(User $user): Prospect
    {
        return Prospect::factory()->create([
            'search_id' => Search::factory()->create(['user_id' => $user->id])->id,
        ]);
    }

    public function test_save_requires_at_least_one_of_name_or_number(): void
    {
        $user = User::factory()->create();
        $prospect = $this->prospectFor($user);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/registered-company", [
                'name' => '',
                'number' => '',
            ])
            ->assertSessionHasErrors(['name', 'number']);
    }

    public function test_save_rejects_invalid_company_number(): void
    {
        $user = User::factory()->create();
        $prospect = $this->prospectFor($user);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/registered-company", [
                'number' => 'ABC',
            ])
            ->assertSessionHasErrors(['number']);
    }

    public function test_operator_can_save_registration_with_number_only(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = $this->prospectFor($user);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/registered-company", [
                'number' => '12345678',
                'note' => 'From website footer',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $prospect->refresh();

        $this->assertNull($prospect->registered_company_name);
        $this->assertSame('12345678', $prospect->registered_company_number);
        $this->assertSame('From website footer', $prospect->registered_company_note);
        $this->assertSame($user->id, $prospect->registered_company_by);
        $this->assertNotNull($prospect->registered_company_at);
        $this->assertNull($prospect->registered_company_cleared_by);
        $this->assertNull($prospect->registered_company_cleared_at);

        Bus::assertDispatched(CheckCompaniesHouseJob::class);
    }

    public function test_first_save_dispatches_check_only_when_never_checked_before(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = $this->prospectFor($user);
        $prospect->update(['companies_house_checked_at' => now()]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/registered-company", [
                'name' => 'North West Dental Holdings Ltd',
            ])
            ->assertRedirect();

        Bus::assertNotDispatched(CheckCompaniesHouseJob::class);
    }

    public function test_operator_can_clear_registration(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = $this->prospectFor($user);
        $prospect->update([
            'registered_company_name' => 'North West Dental Holdings Ltd',
            'registered_company_number' => '12345678',
            'registered_company_note' => 'Manual',
            'registered_company_by' => $user->id,
            'registered_company_at' => now(),
            'companies_house_number' => '12345678',
            'companies_house_status' => 'matched',
            'companies_house_checked_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete("/prospects/{$prospect->id}/registered-company")
            ->assertRedirect()
            ->assertSessionHas('success');

        $prospect->refresh();

        $this->assertNull($prospect->registered_company_name);
        $this->assertNull($prospect->registered_company_number);
        $this->assertNull($prospect->registered_company_note);
        $this->assertNull($prospect->registered_company_by);
        $this->assertNull($prospect->registered_company_at);
        $this->assertSame($user->id, $prospect->registered_company_cleared_by);
        $this->assertNotNull($prospect->registered_company_cleared_at);
        $this->assertSame('12345678', $prospect->companies_house_number);

        Bus::assertNotDispatched(CheckCompaniesHouseJob::class);
    }

    public function test_clear_is_idempotent_when_no_active_registration(): void
    {
        $user = User::factory()->create();
        $prospect = $this->prospectFor($user);

        $this->actingAs($user)
            ->delete("/prospects/{$prospect->id}/registered-company")
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_re_register_after_clear_resets_cleared_audit_fields(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $prospect = $this->prospectFor($user);
        $prospect->update([
            'registered_company_cleared_by' => $user->id,
            'registered_company_cleared_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->post("/prospects/{$prospect->id}/registered-company", [
                'name' => 'North West Dental Holdings Ltd',
            ])
            ->assertRedirect();

        $prospect->refresh();

        $this->assertSame('North West Dental Holdings Ltd', $prospect->registered_company_name);
        $this->assertNull($prospect->registered_company_cleared_by);
        $this->assertNull($prospect->registered_company_cleared_at);

        Bus::assertDispatched(CheckCompaniesHouseJob::class);
    }
}
