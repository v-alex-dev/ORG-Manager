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

    public function test_reference_code_is_generated_automatically(): void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->create(['date_meeting' => '2026-08-21']);
        $service = Service::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'First task',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference_code', 'CFG-2026-001');
    }

    public function test_reference_code_increments_correctly(): void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->create(['date_meeting' => '2026-08-21']);
        $service = Service::factory()->create();

        Task::factory()->create(['reference_code' => 'CFG-2026-001']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'Second task',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference_code', 'CFG-2026-002');
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
            ->patchJson("/api/tasks/{$task->id}/move", [
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
        $user = User::factory()->create();
        $org  = OrgInstance::factory()->cfg()->create();
        $task = Task::factory()->create(['organization_id' => $org->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/move", [
                'org_instance_id' => $org->id,
            ]);
        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_move_task():void
    {
        $task = Task::factory()->create();

        $response = $this->patchJson("/api/tasks/{$task->id}/move", [
            'org_instance_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    public function test_reference_code_resets_for_a_new_meeting_year(): void
    {
        $user    = User::factory()->create();
        $service = Service::factory()->create();

        // Existing tasks from 2026
        Task::factory()->create(['reference_code' => 'CFG-2026-001']);
        Task::factory()->create(['reference_code' => 'CFG-2026-002']);
        Task::factory()->create(['reference_code' => 'CFG-2026-003']);

        // Meeting in January 2027, created before the year changes
        $org = OrgInstance::factory()->cfg()->create(['date_meeting' => '2027-01-20']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'First task of 2027',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference_code', 'CFG-2027-001');
    }

    public function test_reference_code_uses_meeting_year_not_current_year(): void
    {
        $user    = User::factory()->create();
        $service = Service::factory()->create();

        // Meeting far in the past, to prove today's date is ignored
        $org = OrgInstance::factory()->comite()->create(['date_meeting' => '2020-05-10']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'Old meeting task',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference_code', 'COMITE-2020-001');
    }

    public function test_poj_description_is_saved_on_creation(): void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'Task with description',
                'poj_description' => 'This text must be saved.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.poj_description', 'This text must be saved.');

        $this->assertDatabaseHas('tasks', [
            'poj_title'       => 'Task with description',
            'poj_description' => 'This text must be saved.',
        ]);
    }

    // -------------------------------------------------------------------------
// Length limits (POST /api/tasks)
// -------------------------------------------------------------------------

    public function test_title_accepts_100_characters_and_rejects_101(): void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->create();
        $service = Service::factory()->create();

        $ok = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => str_repeat('a', 100),
            ]);
        $ok->assertStatus(201);

        $tooLong = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => str_repeat('a', 101),
            ]);
        $tooLong->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['poj_title']]);
    }

    public function test_description_accepts_500_characters_and_rejects_501(): void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->create();
        $service = Service::factory()->create();

        $ok = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'Valid title',
                'poj_description' => str_repeat('a', 500),
            ]);
        $ok->assertStatus(201);

        $tooLong = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'Another title',
                'poj_description' => str_repeat('a', 501),
            ]);
        $tooLong->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['poj_description']]);
    }

// -------------------------------------------------------------------------
// Archived ORG protection (PATCH /api/tasks/{id}/move)
// -------------------------------------------------------------------------

    public function test_cannot_move_task_from_an_archived_org(): void
    {
        $user    = User::factory()->create();
        $orgFrom = OrgInstance::factory()->cfg()->archived()->create();
        $orgTo   = OrgInstance::factory()->cfg()->create();
        $task    = Task::factory()->create(['organization_id' => $orgFrom->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/move", [
                'org_instance_id' => $orgTo->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot move a task from an archived ORG.');

        $this->assertDatabaseHas('tasks', [
            'id'              => $task->id,
            'organization_id' => $orgFrom->id,
        ]);
    }

    public function test_cannot_move_task_to_an_archived_org(): void
    {
        $user    = User::factory()->create();
        $orgFrom = OrgInstance::factory()->cfg()->create();
        $orgTo   = OrgInstance::factory()->cfg()->archived()->create();
        $task    = Task::factory()->create(['organization_id' => $orgFrom->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/move", [
                'org_instance_id' => $orgTo->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot move a task to an archived ORG.');

        $this->assertDatabaseHas('tasks', [
            'id'              => $task->id,
            'organization_id' => $orgFrom->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Archived ORG protection (POST /api/tasks and PATCH /api/tasks/{id}/status)
    // -------------------------------------------------------------------------

    public function test_cannot_create_a_task_in_an_archived_org(): void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->archived()->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'Should be refused',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot add a task to an archived ORG.');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_cannot_change_status_of_a_task_in_an_archived_org(): void
    {
        $user = User::factory()->create();
        $org  = OrgInstance::factory()->archived()->create();
        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'status'          => 'TODO',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/status");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot change the status of a task in an archived ORG.');

        $this->assertDatabaseHas('tasks', [
            'id'     => $task->id,
            'status' => 'TODO',
        ]);
    }

    public function test_status_response_includes_service_and_reference_task(): void
    {
        $user    = User::factory()->create();
        $service = Service::factory()->create();
        $task    = Task::factory()->create(['service_id' => $service->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.service.id', $service->id)
            ->assertJsonPath('data.org_instance.id', $task->organization_id)
            ->assertJsonPath('data.reference_task', null);
    }

    public function test_non_numeric_org_id_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/orgs/abc/tasks');

        $response->assertStatus(404);
    }
    public function test_can_force_status_to_done(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['status' => 'TODO']);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/status", ['status' => 'DONE'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'DONE');
    }

    public function test_forcing_the_same_status_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['status' => 'TODO']);

        // Simulates a double tap: the status must stay DONE
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user, 'sanctum')
                ->patchJson("/api/tasks/{$task->id}/status", ['status' => 'DONE'])
                ->assertStatus(200)
                ->assertJsonPath('data.status', 'DONE');
        }

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'DONE']);
    }

    public function test_can_force_status_back_to_todo(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->done()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/status", ['status' => 'TODO'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'TODO');
    }

    public function test_status_must_be_a_valid_value(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/tasks/{$task->id}/status", ['status' => 'INVALID'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['status']]);
    }

    // -------------------------------------------------------------------------
// PUT /api/tasks/{id}
// -------------------------------------------------------------------------

    public function test_can_update_a_task(): void
    {
        $user     = User::factory()->create();
        $service  = Service::factory()->create();
        $service2 = Service::factory()->create();
        $task     = Task::factory()->create([
            'service_id'     => $service->id,
            'reference_code' => 'CFG-2026-005',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'service_id'      => $service2->id,
                'poj_title'       => 'Corrected title',
                'poj_description' => 'Corrected description',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.poj_title', 'Corrected title')
            ->assertJsonPath('data.poj_description', 'Corrected description')
            ->assertJsonPath('data.service.id', $service2->id)
            ->assertJsonPath('data.reference_code', 'CFG-2026-005');
    }

    public function test_update_ignores_reference_code_and_status(): void
    {
        $user    = User::factory()->create();
        $service = Service::factory()->create();
        $task    = Task::factory()->create([
            'status'         => 'TODO',
            'reference_code' => 'CFG-2026-005',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'service_id'     => $service->id,
                'poj_title'      => 'New title',
                'reference_code' => 'HACKED-0000-000',
                'status'         => 'DONE',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('tasks', [
            'id'             => $task->id,
            'reference_code' => 'CFG-2026-005',
            'status'         => 'TODO',
        ]);
    }

    public function test_cannot_update_a_task_in_an_archived_org(): void
    {
        $user    = User::factory()->create();
        $service = Service::factory()->create();
        $org     = OrgInstance::factory()->archived()->create();
        $task    = Task::factory()->create([
            'organization_id' => $org->id,
            'poj_title'       => 'Original',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'service_id' => $service->id,
                'poj_title'  => 'Changed',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot update a task in an archived ORG.');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'poj_title' => 'Original']);
    }

    public function test_update_validates_fields_and_length_limits(): void
    {
        $user    = User::factory()->create();
        $service = Service::factory()->create();
        $task    = Task::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['service_id', 'poj_title']]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/tasks/{$task->id}", [
                'service_id' => $service->id,
                'poj_title'  => str_repeat('a', 101),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['poj_title']]);
    }

    public function test_returns_404_when_updating_nonexistent_task(): void
    {
        $user    = User::factory()->create();
        $service = Service::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/tasks/999', ['service_id' => $service->id, 'poj_title' => 'x'])
            ->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_update_task(): void
    {
        $task = Task::factory()->create();

        $this->putJson("/api/tasks/{$task->id}", [])->assertStatus(401);
    }

// -------------------------------------------------------------------------
// DELETE /api/tasks/{id}
// -------------------------------------------------------------------------

    public function test_can_delete_a_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_cannot_delete_a_task_in_an_archived_org(): void
    {
        $user = User::factory()->create();
        $org  = OrgInstance::factory()->archived()->create();
        $task = Task::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot delete a task from an archived ORG.');

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
    }

    public function test_deleting_a_copy_frees_the_original(): void
    {
        $user   = User::factory()->create();
        $cfg    = OrgInstance::factory()->cfg()->create();
        $comite = OrgInstance::factory()->comite()->create();
        $task   = Task::factory()->create(['organization_id' => $cfg->id]);

        $copyId = $this->actingAs($user, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/duplicate", ['org_instance_id' => $comite->id])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/tasks/{$copyId}")
            ->assertStatus(204);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'has_duplicate' => false]);
        $this->assertDatabaseMissing('tasks', ['id' => $copyId]);
    }

    public function test_deleting_an_original_keeps_the_copy(): void
    {
        $user   = User::factory()->create();
        $cfg    = OrgInstance::factory()->cfg()->create();
        $comite = OrgInstance::factory()->comite()->create();
        $task   = Task::factory()->create(['organization_id' => $cfg->id]);

        $copyId = $this->actingAs($user, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/duplicate", ['org_instance_id' => $comite->id])
            ->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/tasks/{$task->id}")
            ->assertStatus(204);

        $this->assertDatabaseHas('tasks', [
            'id'                => $copyId,
            'reference_task_id' => null,
            'has_duplicate'     => true,
        ]);
    }

    public function test_returns_404_when_deleting_nonexistent_task(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/tasks/999')
            ->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_delete_task(): void
    {
        $task = Task::factory()->create();

        $this->deleteJson("/api/tasks/{$task->id}")->assertStatus(401);
    }
}
