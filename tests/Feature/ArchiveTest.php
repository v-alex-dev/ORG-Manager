<?php

namespace Tests\Feature;

use App\Models\OrgInstance;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
   use RefreshDatabase;

    // -------------------------------------------------------------------------
    // GET /api/archives
    // -------------------------------------------------------------------------

    public function test_returns_tasks_from_archived_orgs_only():void
    {
        $user      = User::factory()->create();
        $archived  = OrgInstance::factory()->archived()->create();
        $active    = OrgInstance::factory()->create(['is_archived' => false]);

        Task::factory()->create(['organization_id' => $archived->id]);
        Task::factory()->create(['organization_id' => $active->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/archives');


        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_by_type():void
    {
        $user    = User::factory()->create();
        $cfg     = OrgInstance::factory()->cfg()->archived()->create();
        $comite  = OrgInstance::factory()->comite()->archived()->create();

        Task::factory()->create(['organization_id' => $cfg->id]);
        Task::factory()->create(['organization_id' => $comite->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/archives?type=CFG');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.org_instance.type', 'CFG');
    }

    public function test_filter_by_year():void
    {
        $user  = User::factory()->create();
        $org26 = OrgInstance::factory()->archived()->create(['date_meeting' => '2026-03-01']);
        $org25 = OrgInstance::factory()->archived()->create(['date_meeting' => '2025-03-01']);

        Task::factory()->create(['organization_id' => $org26->id]);
        Task::factory()->create(['organization_id' => $org25->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/archives?year=2026');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_by_poj_title():void
    {
        $user = User::factory()->create();
        $org  = OrgInstance::factory()->archived()->create();

        Task::factory()->create([
            'organization_id' => $org->id,
            'poj_title'       => 'Review the annual budget',
        ]);
        Task::factory()->create([
            'organization_id' => $org->id,
            'poj_title'       => 'HR recruitment plan',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/archives?poj_title=budget');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.poj_title', 'Review the annual budget');
    }

    public function test_filter_by_reference_code():void
    {
        $user = User::factory()->create();
        $org  = OrgInstance::factory()->archived()->create();

        Task::factory()->create([
            'organization_id' => $org->id,
            'reference_code'  => 'CFG-2026-001',
        ]);
        Task::factory()->create([
            'organization_id' => $org->id,
            'reference_code'  => 'CFG-2026-002',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/archives?reference_code=001');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference_code', 'CFG-2026-001');
    }

    public function test_filters_are_cumulative():void
    {
        $user   = User::factory()->create();
        $cfg26  = OrgInstance::factory()->cfg()->archived()->create(['date_meeting' => '2026-03-01']);
        $cfg25  = OrgInstance::factory()->cfg()->archived()->create(['date_meeting' => '2025-03-01']);

        Task::factory()->create([
            'organization_id' => $cfg26->id,
            'poj_title'       => 'Review the budget',
        ]);
        Task::factory()->create([
            'organization_id' => $cfg25->id,
            'poj_title'       => 'Review the budget',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/archives?type=CFG&year=2026&poj_title=budget');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_returns_empty_when_no_match():void
    {
        $user = User::factory()->create();
        $org  = OrgInstance::factory()->archived()->create();

        Task::factory()->create([
            'organization_id' => $org->id,
            'poj_title'       => 'Something else',
        ]);
    }
}
