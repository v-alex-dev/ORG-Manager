<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ArchiveController extends Controller
{
    /**
     * List tasks from archived ORG instances, with optional cumulative filters.
     *
     * GET /api/archives
     *
     * Filters (all optional):
     *   ?type=CFG
     *   ?year=2026
     *   ?poj_title=budget
     *   ?reference_code=CFG-2026
     */

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::in(['CFG','COMITE'])],
            'year' => ['nullable', 'integer', 'digits:4'],
            'poj_title' => ['nullable', 'string', 'max:255'],
            'reference_code' => ['nullable', 'string', 'max:50'],
        ]);

        $tasks = Task::with(['service', 'orgInstance'])
            ->whereHas('orgInstance', function ($query) use ($request) {
                $query->where('is_archived', true);

                if ($request->filled('type')) {
                    $query->where('type', $request->type);
                }

                if ($request->filled('year')) {
                    $query->whereYear('date_meeting', $request->year);
                }
            })

        ;

    }
}
