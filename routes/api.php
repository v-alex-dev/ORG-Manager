<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrgInstanceController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected routes (require a valid Sanctum token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user',    [AuthController::class, 'user']);

    // Services
    Route::get('/services',  [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);

    // ORG instance
    Route::get('/orgs/active', [OrgInstanceController::class, 'active']);
    Route::post('/orgs', [OrgInstanceController::class, 'store']);
    Route::put('/orgs/{id}/archive', [OrgInstanceController::class, 'archive']);
});
