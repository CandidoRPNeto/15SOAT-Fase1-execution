<?php

use App\Http\Controllers\Api\V1\ExecutionController;
use App\Http\Middleware\TrustGatewayIdentity;
use Illuminate\Support\Facades\Route;

// Every route sits behind Traefik ForwardAuth (ADR-013). Shop floor = staff only.
Route::prefix('v1/executions')->middleware([TrustGatewayIdentity::class, 'role:mechanic,receptionist'])->group(function () {
    Route::get('/', [ExecutionController::class, 'index']);
    Route::get('/{serviceOrderId}', [ExecutionController::class, 'show'])->whereNumber('serviceOrderId');

    Route::middleware('role:mechanic')->whereNumber('serviceOrderId')->group(function () {
        Route::post('/{serviceOrderId}/diagnosis/start', [ExecutionController::class, 'startDiagnosis']);
        Route::post('/{serviceOrderId}/diagnosis/finish', [ExecutionController::class, 'finishDiagnosis']);
        Route::post('/{serviceOrderId}/repair/start', [ExecutionController::class, 'startRepair']);
        Route::post('/{serviceOrderId}/repair/finish', [ExecutionController::class, 'finishRepair']);
    });
});
