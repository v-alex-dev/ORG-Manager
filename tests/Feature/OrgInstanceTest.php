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
    }
}
