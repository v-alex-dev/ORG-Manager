<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\OrgInstance;
use App\Models\User;

class OrgInstanceTest extends TestCase
{
    use RefreshDatabase;
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

        $response->assertStatus(200)
            ->assertJsonPath('data.0.date_meeting', '2026-07-15T00:00:00.000000Z')
            ->assertJsonPath('data.1.date_meeting', '2026-08-20T00:00:00.000000Z')
            ->assertJsonPath('data.2.date_meeting', '2026-09-01T00:00:00.000000Z');
    }

    public function test_type_is_required_for_active_orgs():void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orgs/active');

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['type']]);
    }

    public function test_type_must_be_valid_for_active_orgs():void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orgs/active?type=INVALID');

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['type']]);
    }

    public function test_unauthenticated_user_cannot_list_active_orgs(): void
    {
        $response = $this->getJson('/api/orgs/active?type=CFG');

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // POST /api/orgs
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_create_an_org():void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orgs', [
                'type'            => 'CFG',
                'recurrence_type' => 'HEBDO',
                'date_meeting'    => '2026-08-21',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'CFG')
            ->assertJsonPath('data.recurrence_type', 'HEBDO')
            ->assertJsonPath('data.is_archived', false);

        $this->assertDatabaseHas('org_instances', [
            'type'         => 'CFG',
            'date_meeting' => '2026-08-21',
        ]);
    }

    public function test_cannot_create_org_with_missing_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orgs', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['type', 'recurrence_type', 'date_meeting']]);
    }

    public function test_cannot_create_org_with_invalid_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/orgs', [
                'type'            => 'INVALID',
                'recurrence_type' => 'HEBDO',
                'date_meeting'    => '2026-08-21',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['type']]);
    }

    public function test_unauthenticated_user_cannot_create_org(): void
    {
        $response = $this->postJson('/api/orgs', [
            'type'            => 'CFG',
            'recurrence_type' => 'HEBDO',
            'date_meeting'    => '2026-08-21',
        ]);

        $response->assertStatus(401);

    }

    // -------------------------------------------------------------------------
    // PUT /api/orgs/{id}/archive
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_archive_an_org():void
    {
        $user = User::factory()->create();
        $org  = OrgInstance::factory()->create(['is_archived' => false]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/orgs/{$org->id}/archive");

        $this->assertDatabaseHas('org_instances', [
            'id'          => $org->id,
            'is_archived' => true,
        ]);
    }

    public function test_cannot_archive_an_already_archived_org(): void
    {
        $user = User::factory()->create();
        $org  = OrgInstance::factory()->create(['is_archived' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/orgs/{$org->id}/archive");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This ORG is already archived.');
    }

    public function test_cannot_archive_a_nonexistent_org(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/orgs/999/archive');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_archive_org(): void
    {
        $org = OrgInstance::factory()->create();

        $response = $this->putJson("/api/orgs/{$org->id}/archive");

        $response->assertStatus(401);
    }
}
