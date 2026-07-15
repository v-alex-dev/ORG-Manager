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

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // POST /api/tasks
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_create_a_task():void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'Review the budget',
            ]);
        $response->assertStatus(201)
            ->assertJsonPath('data.poj_title', 'Review the budget')
            ->assertJsonPath('data.status', 'TODO');

        $this->assertDatabaseHas('tasks', [
            'organization_id' => $org->id,
            'poj_title'       => 'Review the budget',
        ]);

    }

    public function test_reference_code_is_generated_automatically():void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'First task',
            ]);

        $year = now()->format('Y');
        $response->assertStatus(201)
            ->assertJsonPath('data.reference_code', "CFG-{$year}-001");
    }

    public function test_reference_code_increments_correctly():void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->create();
        $service = Service::factory()->create();

        $year = now()->format('Y');
        Task::factory()->create(['reference_code' => "CFG-{$year}-001"]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'Second task',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference_code', "CFG-{$year}-002");
    }

    public function test_cannot_create_task_with_missing_fields():void
    {
        $user    = User::factory()->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => 999,
                'service_id'      => $service->id,
                'poj_title'       => 'Test',
            ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_create_task(): void
    {
        $response = $this->postJson('/api/tasks', []);

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/tasks/{id}/status
    // -------------------------------------------------------------------------

    public function test_can_toggle_task_status_from_todo_to_done():void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['status' => 'TODO']);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'DONE');
    }


    public function test_can_toggle_task_status_from_done_to_todo():void
    {
        $user = User::factory()->create();
        $task = Task::factory()->done()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'TODO');
    }

    public function test_returns_404_when_toggling_nonexistent_task():void
    {
        $user = User::factory()->create();


        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/999/status');


        $response->assertStatus(404);

    }

    public function test_unauthenticated_user_cannot_toggle_status():void
    {
        $task = Task::factory()->create();

        $response = $this->patchJson("/api/tasks/{$task->id}/status");

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/tasks/{id}/move
    // -------------------------------------------------------------------------

    public function test_can_move_task_to_another_org_instance():void
    {
        $user    = User::factory()->create();
        $orgFrom = OrgInstance::factory()->cfg()->create();
        $orgTo   = OrgInstance::factory()->cfg()->create();
        $task    = Task::factory()->create(['organization_id' => $orgFrom->id]);

        $response = $this->actingAs($user, 'sanctum')
            >patchJson("/api/tasks/{$task->id}/move", [
                'org_instance_id' => $orgTo->id,
            ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('tasks', [
            'id'              => $task->id,
            'organization_id' => $orgTo->id,
        ]);
    }

    public function test_reference_code_is_preserved_after_move():void
    {
        $user    = User::factory()->create();
        $orgFrom = OrgInstance::factory()->cfg()->create();
        $orgTo   = OrgInstance::factory()->cfg()->create();
        $task    = Task::factory()->create([
            'organization_id' => $orgFrom->id,
            'reference_code'  => 'CFG-2026-007',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/move", [
                'org_instance_id' => $orgTo->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.reference_code', 'CFG-2026-007');
    }

    public function test_status_is_preserved_after_move():void
    {
        $user    = User::factory()->create();
        $orgFrom = OrgInstance::factory()->cfg()->create();
        $orgTo   = OrgInstance::factory()->cfg()->create();
        $task    = Task::factory()->done()->create(['organization_id' => $orgFrom->id]);


        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/move", [
                'org_instance_id' => $orgTo->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'DONE');
    }

    public function test_cannot_move_task_to_different_org_type():void
    {
        $user    = User::factory()->create();
        $orgFrom = OrgInstance::factory()->cfg()->create();
        $orgTo   = OrgInstance::factory()->comite()->create();
        $task    = Task::factory()->create(['organization_id' => $orgFrom->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/move", [
                'org_instance_id' => $orgTo->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot move a task to a different ORG type.');
    }

    public function test_cannot_move_task_to_the_same_org_instance():void
    {

    }
}
