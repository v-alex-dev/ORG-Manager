<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgInstance;
use App\Models\Task;
use App\Services\ReferenceCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $tasks = Task::with(['service', 'orgInstance'])
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
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'org_instance_id' => ['required', 'integer', 'exists:org_instances,id'],
            'service_id'      => ['required', 'integer', 'exists:services,id'],
            'poj_title'       => ['required', 'string', 'max:255'],
            'poj_description' => ['nullable', 'string'],
        ]);

        $org = OrgInstance::findOrFail($request->org_instance_id);

        $referenceCode =  $this->referenceCodeService->generate(
            $org->type,
            (int) now()->format('Y')
        );

        $task = Task::create([
            'organization_id' => $org->id,
            'service_id'      => $request->service_id,
            'poj_title'       => $request->poj_title,
            'poj_description' => $request->poj_description,
            'status'          => 'TODO',
            'reference_code'  => $referenceCode,
        ]);

        $task->load(['service', 'orgInstance']);

        return response()->json([
            'data'=>$task,
        ], 201);

    }

    /**
     * Toggle the task status between TODO and DONE.
     *
     * PATCH /api/tasks/{id}/status
     */
    public function updateStatus(int $id): JsonResponse
    {
        $task = Task::findOrFail($id);

        $task->update([
            'status' => $task->status === 'TODO' ? 'DONE' : 'TODO'
        ]);

        return response()->json([
            'data' => $task,
        ], 202);
    }

    /**
     * Move a task to another ORG instance (same type).
     * The reference_code is preserved.
     * The status is preserved.
     *
     * PATCH /api/tasks/{id}/move
     */

    public function move(Request $request,int $id): JsonResponse
    {
        $task = Task::findOrFail($id);
    }
}
