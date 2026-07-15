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


    }
}
