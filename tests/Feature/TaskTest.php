<?php

namespace Tests\Feature;

use App\Models\OrgInstance;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use refreshDatabase;


    // -------------------------------------------------------------------------
    // GET /api/orgs/{id}/tasks
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_list_tasks_for_an_org():void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->create();
        $service = Service::factory()->create();

        Task::factory()->count(3)->create([
            'organization_id' => $org->id,
            'service_id'      => $service->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/orgs/{$org->id}/tasks");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'poj_title', 'status', 'reference_code', 'service', 'org_instance']],
            ]);
    }
}
