<?php

namespace Tests\Feature;

use App\Models\OrgInstance;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function duplicate(User $user, Task $task, int $orgId)
    {
        return $this->actingAs($user, 'sanctum')
            ->postJson("/api/tasks/{$task->id}/duplicate", [
                'org_instance_id' => $orgId,
            ]);
    }

    public function test_can_duplicate_a_task_to_another_org_type(): void
    {
        $user    = User::factory()->create();
        $service = Service::factory()->create();
        $cfg     = OrgInstance::factory()->cfg()->create();
        $comite  = OrgInstance::factory()->comite()->create(['date_meeting' => '2027-01-20']);
        $task    = Task::factory()->done()->create([
            'organization_id' => $cfg->id,
            'service_id'      => $service->id,
            'poj_title'       => 'Review the budget',
            'poj_description' => 'Some description',
            'reference_code'  => 'CFG-2026-012',
        ]);

        $response = $this->duplicate($user, $task, $comite->id);

        $response->assertStatus(201)
            ->assertJsonPath('data.reference_code', 'COMITE-2027-001')
            ->assertJsonPath('data.poj_title', 'Review the budget')
            ->assertJsonPath('data.poj_description', 'Some description')
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.status', 'TODO')
            ->assertJsonPath('data.has_duplicate', true)
            ->assertJsonPath('data.reference_task.id', $task->id)
            ->assertJsonPath('data.reference_task.reference_code', 'CFG-2026-012')
            ->assertJsonPath('data.org_instance.id', $comite->id);
    }

    public function test_original_task_is_flagged_as_duplicated(): void
    {
        $user   = User::factory()->create();
        $cfg    = OrgInstance::factory()->cfg()->create();
        $comite = OrgInstance::factory()->comite()->create();
        $task   = Task::factory()->create(['organization_id' => $cfg->id]);

        $this->duplicate($user, $task, $comite->id)->assertStatus(201);

        $this->assertDatabaseHas('tasks', [
            'id'                => $task->id,
            'has_duplicate'     => true,
            'reference_task_id' => null,
            'organization_id'   => $cfg->id,
        ]);
    }

    public function test_cannot_duplicate_to_the_same_org_type(): void
    {
        $user   = User::factory()->create();
        $cfg1   = OrgInstance::factory()->cfg()->create();
        $cfg2   = OrgInstance::factory()->cfg()->create();
        $task   = Task::factory()->create(['organization_id' => $cfg1->id]);

        $this->duplicate($user, $task, $cfg2->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'A task can only be duplicated to a different ORG type.');
    }

    public function test_cannot_duplicate_a_task_twice(): void
    {
        $user    = User::factory()->create();
        $cfg     = OrgInstance::factory()->cfg()->create();
        $comite1 = OrgInstance::factory()->comite()->create();
        $comite2 = OrgInstance::factory()->comite()->create();
        $task    = Task::factory()->create(['organization_id' => $cfg->id]);

        $this->duplicate($user, $task, $comite1->id)->assertStatus(201);

        $this->duplicate($user, $task->fresh(), $comite2->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'This task has already been duplicated.');

        $this->assertDatabaseCount('tasks', 2);
    }

    public function test_cannot_duplicate_a_copy(): void
    {
        $user   = User::factory()->create();
        $cfg    = OrgInstance::factory()->cfg()->create();
        $cfg2   = OrgInstance::factory()->cfg()->create();
        $comite = OrgInstance::factory()->comite()->create();
        $task   = Task::factory()->create(['organization_id' => $cfg->id]);

        $copyId = $this->duplicate($user, $task, $comite->id)->json('data.id');
        $copy   = Task::findOrFail($copyId);

        $this->duplicate($user, $copy, $cfg2->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'This task has already been duplicated.');
    }

    public function test_cannot_duplicate_from_an_archived_org(): void
    {
        $user   = User::factory()->create();
        $cfg    = OrgInstance::factory()->cfg()->archived()->create();
        $comite = OrgInstance::factory()->comite()->create();
        $task   = Task::factory()->create(['organization_id' => $cfg->id]);

        $this->duplicate($user, $task, $comite->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot duplicate a task from an archived ORG.');
    }

    public function test_cannot_duplicate_to_an_archived_org(): void
    {
        $user   = User::factory()->create();
        $cfg    = OrgInstance::factory()->cfg()->create();
        $comite = OrgInstance::factory()->comite()->archived()->create();
        $task   = Task::factory()->create(['organization_id' => $cfg->id]);

        $this->duplicate($user, $task, $comite->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot duplicate a task to an archived ORG.');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'has_duplicate' => false]);
    }

    public function test_duplicate_requires_an_existing_target_org(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();

        $this->duplicate($user, $task, 999)
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['org_instance_id']]);
    }

    public function test_returns_404_when_duplicating_nonexistent_task(): void
    {
        $user   = User::factory()->create();
        $comite = OrgInstance::factory()->comite()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks/999/duplicate', ['org_instance_id' => $comite->id])
            ->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_duplicate(): void
    {
        $task = Task::factory()->create();

        $this->postJson("/api/tasks/{$task->id}/duplicate", ['org_instance_id' => 1])
            ->assertStatus(401);
    }

    public function test_new_task_has_no_duplicate_and_no_reference_task(): void
    {
        $user    = User::factory()->create();
        $org     = OrgInstance::factory()->cfg()->create();
        $service = Service::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/tasks', [
                'org_instance_id' => $org->id,
                'service_id'      => $service->id,
                'poj_title'       => 'Normal task',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.has_duplicate', false)
            ->assertJsonPath('data.reference_task', null);
    }
}
