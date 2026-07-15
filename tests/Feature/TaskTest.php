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

    public function test_tasks_only_belong_to_the_requested_org():void
    {
        $user = User::factory()->create();
        $org1 = OrgInstance::factory()->create();
        $org2 = OrgInstance::factory()->create();

        Task::factory()->count(2)->create(['organization_id' => $org1->id]);
        Task::factory()->count(3)->create(['organization_id' => $org2->id]);


        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/orgs/{$org1->id}/tasks");


        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_returns_404_for_nonexistent_org():void
    {
        $user = User::factory()->create();


        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orgs/999/tasks');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_list_tasks():void
    {
        $org = OrgInstance::factory()->create();

        $response = $this->getJson("/api/orgs/{$org->id}/tasks");
    }
}
