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

    }
}
