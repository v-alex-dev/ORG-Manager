<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrgInstanceController extends Controller
{
    /**
     * List all active (non-archived) ORG instances for a given type.
     *
     * GET /api/orgs/active?type=CFG
     * GET /api/orgs/active?type=COMITE
     */
    public function active(Request $request)
    {
        $request->validate([
            'type' => ['required', Rule::in(['CFG', 'COMITE'])],
        ]);

        $org = OrgInstance::where('type', $request->type)
            ->where('is_archived', false)
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => $org,
        ]);
    }

    /**
     * Create a new ORG instance.
     *
     * POST /api/orgs
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => ['required', Rule::in(['CFG', 'COMITE'])],
            'recurrence_type' => ['required', Rule::in(['HEBDO', 'OCCASIONNEL'])],
            'date_meeting' => ['required', 'date'],
        ]);


    }
}
