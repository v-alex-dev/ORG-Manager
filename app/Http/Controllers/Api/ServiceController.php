<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ForceJsonResponse;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(): ForceJsonResponse
    {
        $services = Service::orderBy('name')->get();
        return response()->json([
            'data' => $services,
        ]);
    }

    public function store(Request $request): ForceJsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:services,name'],
        ]);

        $service = Service::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'data' => $service,
        ], 201);
    }


}
