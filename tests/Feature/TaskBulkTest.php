<?php

namespace Tests\Feature;

use App\Models\OrgInstance;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskBulkTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // PATCH /api/tasks/bulk-done
    // -------------------------------------------------------------------------

    public function test_can_mark_several_tasks_as_done(): void
    {
        $user  = User::factory()->create();
        $tasks = Task::factory()->count(3)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-done', [
                'task_ids' => $tasks->pluck('id')->all(),
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.status', 'DONE');

        foreach ($tasks as $task) {
            $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'DONE']);
        }
    }

    public function test_bulk_done_does_not_toggle_tasks_already_done(): void
    {
        $user = User::factory()->create();
        $todo = Task::factory()->create();
        $done = Task::factory()->done()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-done', ['task_ids' => [$todo->id, $done->id]])
            ->assertStatus(200);

        $this->assertDatabaseHas('tasks', ['id' => $done->id, 'status' => 'DONE']);
        $this->assertDatabaseHas('tasks', ['id' => $todo->id, 'status' => 'DONE']);
    }

    public function test_bulk_done_changes_nothing_if_one_task_is_archived(): void
    {
        $user     = User::factory()->create();
        $active   = Task::factory()->create();
        $archived = Task::factory()->create([
            'organization_id' => OrgInstance::factory()->archived()->create()->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-done', ['task_ids' => [$active->id, $archived->id]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot change the status of a task in an archived ORG.');

        $this->assertDatabaseHas('tasks', ['id' => $active->id, 'status' => 'TODO']);
        $this->assertDatabaseHas('tasks', ['id' => $archived->id, 'status' => 'TODO']);
    }

    public function test_bulk_done_validates_the_ids(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-done', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['task_ids']]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-done', ['task_ids' => [999]])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['task_ids.0']]);
    }

    public function test_unauthenticated_user_cannot_bulk_done(): void
    {
        $this->patchJson('/api/tasks/bulk-done', ['task_ids' => [1]])->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/tasks/bulk-move
    // -------------------------------------------------------------------------

    public function test_can_move_several_tasks_to_an_earlier_meeting(): void
    {
        $user   = User::factory()->create();
        $from   = OrgInstance::factory()->cfg()->create(['date_meeting' => '2026-09-21']);
        $to     = OrgInstance::factory()->cfg()->create(['date_meeting' => '2026-09-07']);
        $tasks  = Task::factory()->count(2)->create(['organization_id' => $from->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-move', [
                'task_ids'        => $tasks->pluck('id')->all(),
                'org_instance_id' => $to->id,
            ])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        foreach ($tasks as $task) {
            $this->assertDatabaseHas('tasks', ['id' => $task->id, 'organization_id' => $to->id]);
        }
    }

    public function test_bulk_move_preserves_reference_code_and_status(): void
    {
        $user = User::factory()->create();
        $from = OrgInstance::factory()->cfg()->create();
        $to   = OrgInstance::factory()->cfg()->create();
        $task = Task::factory()->done()->create([
            'organization_id' => $from->id,
            'reference_code'  => 'CFG-2026-007',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-move', [
                'task_ids'        => [$task->id],
                'org_instance_id' => $to->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.0.reference_code', 'CFG-2026-007')
            ->assertJsonPath('data.0.status', 'DONE');
    }

    public function test_bulk_move_rejects_a_different_org_type_and_changes_nothing(): void
    {
        $user   = User::factory()->create();
        $cfg    = OrgInstance::factory()->cfg()->create();
        $comite = OrgInstance::factory()->comite()->create();
        $task   = Task::factory()->create(['organization_id' => $cfg->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-move', [
                'task_ids'        => [$task->id],
                'org_instance_id' => $comite->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot move a task to a different ORG type.');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'organization_id' => $cfg->id]);
    }

    public function test_bulk_move_rejects_an_archived_target(): void
    {
        $user = User::factory()->create();
        $from = OrgInstance::factory()->cfg()->create();
        $to   = OrgInstance::factory()->cfg()->archived()->create();
        $task = Task::factory()->create(['organization_id' => $from->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-move', [
                'task_ids'        => [$task->id],
                'org_instance_id' => $to->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot move a task to an archived ORG.');
    }

    public function test_bulk_move_rejects_a_task_from_an_archived_org(): void
    {
        $user     = User::factory()->create();
        $archived = OrgInstance::factory()->cfg()->archived()->create();
        $active   = OrgInstance::factory()->cfg()->create();
        $to       = OrgInstance::factory()->cfg()->create();
        $frozen   = Task::factory()->create(['organization_id' => $archived->id]);
        $free     = Task::factory()->create(['organization_id' => $active->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-move', [
                'task_ids'        => [$free->id, $frozen->id],
                'org_instance_id' => $to->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot move a task from an archived ORG.');

        // The valid task was not moved either
        $this->assertDatabaseHas('tasks', ['id' => $free->id, 'organization_id' => $active->id]);
    }

    public function test_bulk_move_rejects_a_task_already_in_the_target(): void
    {
        $user = User::factory()->create();
        $org  = OrgInstance::factory()->cfg()->create();
        $task = Task::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-move', [
                'task_ids'        => [$task->id],
                'org_instance_id' => $org->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The task is already in this ORG.');
    }

    public function test_bulk_move_validates_the_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/tasks/bulk-move', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['task_ids', 'org_instance_id']]);
    }

    public function php(): void
    {
        $this->patchJson('/api/tasks/bulk-move', [])->assertStatus(401);
    }
}
