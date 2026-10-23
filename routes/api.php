<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\IepIntakeController;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status'  => 'ok',
        'service' => 'orvis-iep-api',
        'env'     => app()->environment(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Authenticated routes (Sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Frontend bootstrap / identity
    Route::get('/me', function (Request $request) {
        return response()->json([
            'id'    => $request->user()->id,
            'name'  => $request->user()->name,
            'email' => $request->user()->email,
        ]);
    });

    // IEP Intakes
    Route::get('/intakes', [IepIntakeController::class, 'index']);
    Route::get('/intakes/draft', [IepIntakeController::class, 'getDraft']);
    Route::post('/intakes', [IepIntakeController::class, 'store']);
    Route::get('/intakes/{intake}', [IepIntakeController::class, 'show']);
    Route::patch('/intakes/{intake}', [IepIntakeController::class, 'update']);
    Route::delete('/intakes/{intake}', [IepIntakeController::class, 'destroy']);
    Route::post('/intakes/{intake}/submit', [IepIntakeController::class, 'submit']);
    Route::post('/intakes/{sourceIntake}/seed', [IepIntakeController::class, 'seed']);
});
