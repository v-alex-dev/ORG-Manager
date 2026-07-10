<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\OrgInstance;
use App\Models\User;

class OrgInstanceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // GET /api/orgs/active?type=CFG
    // -------------------------------------------------------------------------
    public function test_authenticated_user_can_list_active_orgs(): void
    {
        $user = User::factory()->create();

        OrgInstance::factory()->create(['type' => 'CFG', 'is_archived' => false]);
        OrgInstance::factory()->create(['type' => 'CFG', 'is_archived' => false]);
        OrgInstance::factory()->create(['type' => 'CFG', 'is_archived' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orgs/active?type=CFG');

        $response->assertStatus(200)
            ->assertJsonCount(2,'data');
    }

    public function test_active_orgs_are_filtered_by_type():void {
        $user = User::factory()->create();

        OrgInstance::factory()->create(['type' => 'CFG']);
        OrgInstance::factory()->create(['type' => 'COMITE']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orgs/active?type=CFG');

        $response->assertStatus(200)
            ->assertJsonCount(1,'data')
            ->assertJsonPath('data.0.type', 'CFG');
    }

    public function test_active_orgs_are_ordered_by_date_ascending():void
    {
        $user = User::factory()->create();

        OrgInstance::factory()->create(['type' => 'CFG', 'date_meeting' => '2026-08-20']);
        OrgInstance::factory()->create(['type' => 'CFG', 'date_meeting' => '2026-07-15']);
        OrgInstance::factory()->create(['type' => 'CFG', 'date_meeting' => '2026-09-01']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orgs/active?type=CFG');
    }
}
