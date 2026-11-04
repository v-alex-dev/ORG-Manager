<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\OrgInstance;
use App\Models\Task;
use App\Services\ReferenceCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;
use App\Http\Requests\MoveTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\DuplicateTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Requests\BulkDoneTasksRequest;
use App\Http\Requests\BulkMoveTasksRequest;

class TaskController extends Controller
{
    public function __construct(
        private readonly ReferenceCodeService $referenceCodeService,
    ){}

    /**
     * List all tasks for a given ORG instance, with service and org_instance relations.
     *
     * GET /api/orgs/{id}/tasks
     */
    public function index(int $id): JsonResponse
    {
        $org = OrgInstance::findOrFail($id);

        $tasks = Task::with(['service', 'orgInstance', 'referenceTask:id,reference_code'])
            ->where('organization_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data'=>$tasks,
        ]);

    }

    /**
     * Create a new task.
     * The reference_code is generated automatically and is immutable.
     *
     * POST /api/tasks
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        $org = OrgInstance::findOrFail($data['org_instance_id']);

        $org->ensureNotArchived('Cannot add a task to an archived ORG.');

        // The year comes from the meeting date, never from today's date
        $referenceCode = $this->referenceCodeService->generate(
            $org->type,
            $org->date_meeting->year
        );

        $task = Task::create([
            'organization_id' => $org->id,
            'service_id'      => $data['service_id'],
            'poj_title'       => $data['poj_title'],
            'poj_description' => $data['poj_description'] ?? null,
            'status'          => 'TODO',
            'reference_code'  => $referenceCode,
        ]);

        $task->load(['service', 'orgInstance', 'referenceTask:id,reference_code']);

        return response()->json([
            'data' => $task,
        ], 201);
    }

    /**
     * Change the task status.
     *
     * Without a body, the status is toggled between TODO and DONE.
     * With a "status" field, the given value is applied, so a double tap
     * or a network retry always gives the same result (idempotent).
     *
     * PATCH /api/tasks/{id}/status
     */
    public function updateStatus(UpdateTaskStatusRequest $request, int $id): JsonResponse
    {
        $task = Task::with('orgInstance')->findOrFail($id);

        // A task of an archived ORG is frozen
        $task->orgInstance->ensureNotArchived('Cannot change the status of a task in an archived ORG.');

        // Use the requested status, or toggle when none is given
        $newStatus = $request->validated('status')
            ?? ($task->status === 'TODO' ? 'DONE' : 'TODO');

        $task->update(['status' => $newStatus]);

        $task->load(['service', 'referenceTask:id,reference_code']);

        return response()->json([
            'data' => $task,
        ]);
    }

    /**
     * Move a task to another ORG instance (same type).
     * The reference_code is preserved.
     * The status is preserved.
     *
     * PATCH /api/tasks/{id}/move
     */

    public function move(MoveTaskRequest $request, int $id): JsonResponse
    {
        $task = Task::with('orgInstance')->findOrFail($id);

        $sourceOrg = $task->orgInstance;
        $targetOrg = OrgInstance::findOrFail($request->validated('org_instance_id'));

        if ($sourceOrg->id === $targetOrg->id) {
            return response()->json([
                'message' => 'The task is already in this ORG.',
            ], 422);
        }

        // A task of an archived ORG is frozen, and an archived ORG cannot receive new tasks
        $sourceOrg->ensureNotArchived('Cannot move a task from an archived ORG.');
        $targetOrg->ensureNotArchived('Cannot move a task to an archived ORG.');

        // Both instances must be of the same type (CFG to CFG, COMITE to COMITE)
        if ($sourceOrg->type !== $targetOrg->type) {
            return response()->json([
                'message' => 'Cannot move a task to a different ORG type.',
            ], 422);
        }

        $task->update(['organization_id' => $targetOrg->id]);

        $task->load(['service', 'orgInstance', 'referenceTask:id,reference_code']);

        return response()->json([
            'data' => $task,
        ]);
    }

    /**
     * Duplicate a task into an ORG of a different type (CFG to COMITE or the reverse).
     * The copy gets its own reference code and keeps a link to the original task.
     *
     * POST /api/tasks/{id}/duplicate
     */
    public function duplicate(DuplicateTaskRequest $request, int $id): JsonResponse
    {
        $original  = Task::with('orgInstance')->findOrFail($id);
        $sourceOrg = $original->orgInstance;
        $targetOrg = OrgInstance::findOrFail($request->validated('org_instance_id'));

        // A task can only be duplicated once (this also blocks duplicating a copy)
        if ($original->has_duplicate) {
            return response()->json([
                'message' => 'This task has already been duplicated.',
            ], 422);
        }

        $sourceOrg->ensureNotArchived('Cannot duplicate a task from an archived ORG.');
        $targetOrg->ensureNotArchived('Cannot duplicate a task to an archived ORG.');

        if ($sourceOrg->type === $targetOrg->type) {
            return response()->json([
                'message' => 'A task can only be duplicated to a different ORG type.',
            ], 422);
        }

        // Both writes succeed together or not at all
        $copy = DB::transaction(function () use ($original, $targetOrg) {
            $referenceCode = $this->referenceCodeService->generate(
                $targetOrg->type,
                $targetOrg->date_meeting->year
            );

            $copy = Task::create([
                'organization_id'   => $targetOrg->id,
                'service_id'        => $original->service_id,
                'poj_title'         => $original->poj_title,
                'poj_description'   => $original->poj_description,
                'status'            => 'TODO',
                'has_duplicate'     => true,
                'reference_task_id' => $original->id,
                'reference_code'    => $referenceCode,
            ]);

            $original->update(['has_duplicate' => true]);

            return $copy;
        });

        $copy->load(['service', 'orgInstance', 'referenceTask:id,reference_code']);

        return response()->json([
            'data' => $copy,
        ], 201);
    }

    /**
     * Edit the title, the description and the service of a task.
     * The reference_code and the status are never changed here.
     *
     * PUT /api/tasks/{id}
     */
    public function update(UpdateTaskRequest $request, int $id): JsonResponse
    {
        $task = Task::with('orgInstance')->findOrFail($id);

        $task->orgInstance->ensureNotArchived('Cannot update a task in an archived ORG.');

        $task->update([
            'service_id'      => $request->validated('service_id'),
            'poj_title'       => $request->validated('poj_title'),
            'poj_description' => $request->validated('poj_description'),
        ]);

        $task->load(['service', 'orgInstance', 'referenceTask:id,reference_code']);

        return response()->json([
            'data' => $task,
        ]);
    }

    /**
     * Permanently delete a task.
     * If the task is a copy, its original can be duplicated again.
     *
     * DELETE /api/tasks/{id}
     */
    public function destroy(int $id): Response
    {
        $task = Task::with('orgInstance')->findOrFail($id);

        $task->orgInstance->ensureNotArchived('Cannot delete a task from an archived ORG.');

        // Both writes succeed together or not at all
        DB::transaction(function () use ($task) {
            // Only a copy has a reference_task_id: free its original
            if ($task->reference_task_id !== null) {
                Task::whereKey($task->reference_task_id)->update(['has_duplicate' => false]);
            }

            $task->delete();
        });

        return response()->noContent();
    }

    /**
     * Mark several tasks as DONE at once.
     * Every task is checked first, so nothing changes if one of them is frozen.
     * Tasks that are already DONE stay DONE (no toggle).
     *
     * PATCH /api/tasks/bulk-done
     */
    public function bulkDone(BulkDoneTasksRequest $request): JsonResponse
    {
        $ids = $request->validated('task_ids');

        $tasks = Task::with('orgInstance')->whereIn('id', $ids)->get();

        foreach ($tasks as $task) {
            $task->orgInstance->ensureNotArchived('Cannot change the status of a task in an archived ORG.');
        }

        // One single query: it either works for all tasks or for none
        Task::whereIn('id', $ids)->update(['status' => 'DONE']);

        $tasks = Task::with(['service', 'orgInstance', 'referenceTask:id,reference_code'])
            ->whereIn('id', $ids)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => $tasks,
        ]);
    }

    /**
     * Move several tasks to another ORG instance (same type, any date).
     * The reference codes and the statuses are preserved.
     *
     * PATCH /api/tasks/bulk-move
     */
    public function bulkMove(BulkMoveTasksRequest $request): JsonResponse
    {
        $ids       = $request->validated('task_ids');
        $targetOrg = OrgInstance::findOrFail($request->validated('org_instance_id'));

        $targetOrg->ensureNotArchived('Cannot move a task to an archived ORG.');

        $tasks = Task::with('orgInstance')->whereIn('id', $ids)->get();

        // Check every task before writing anything
        foreach ($tasks as $task) {
            if ($task->organization_id === $targetOrg->id) {
                return response()->json([
                    'message' => 'The task is already in this ORG.',
                ], 422);
            }

            $task->orgInstance->ensureNotArchived('Cannot move a task from an archived ORG.');

            if ($task->orgInstance->type !== $targetOrg->type) {
                return response()->json([
                    'message' => 'Cannot move a task to a different ORG type.',
                ], 422);
            }
        }

        Task::whereIn('id', $ids)->update(['organization_id' => $targetOrg->id]);

        $tasks = Task::with(['service', 'orgInstance', 'referenceTask:id,reference_code'])
            ->whereIn('id', $ids)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'data' => $tasks,
        ]);
    }
}
