<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ApiKeyController;
use App\Http\Controllers\Api\V1\EventLogController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\OpsController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\WebhookReplayController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\MunicipalityController;
use App\Http\Controllers\Api\V1\DeadlineController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\V1\CapsWebhookController;

// -------------------------------------------------------------------------
// CAPS webhook (Layer 3 – Status Echo-back). HMAC-verified, no Sanctum
// token needed — CAPS signs the payload with a shared secret.
// -------------------------------------------------------------------------
Route::post('v1/webhooks/caps', [CapsWebhookController::class, 'handle'])
    ->name('api.webhooks.caps');

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResource('roles', RoleController::class)->only(['index', 'show']);
    Route::apiResource('companies', CompanyController::class)->only(['index', 'show']);
    Route::apiResource('municipalities', MunicipalityController::class)->only(['index', 'show']);
    Route::apiResource('deadlines', DeadlineController::class)->only(['index', 'show']);
    Route::apiResource('uploads', UploadController::class)->only(['index', 'show'])->names('api.uploads');
    Route::get('uploads/premium-batch', [UploadController::class, 'premiumBatchDetailedInfo']);

    // Tenant + platform management APIs (internal authenticated users).
    Route::get('/tenants/current', [TenantController::class, 'current']);
    Route::patch('/tenants/current/settings', [TenantController::class, 'updateSettings']);

    Route::get('/api-keys', [ApiKeyController::class, 'index']);
    Route::post('/api-keys', [ApiKeyController::class, 'store']);
    Route::delete('/api-keys/{id}', [ApiKeyController::class, 'destroy']);

    Route::get('/workflows', [WorkflowController::class, 'index']);
    Route::post('/workflows', [WorkflowController::class, 'store']);
    Route::post('/workflows/{id}/publish', [WorkflowController::class, 'publish']);
    Route::post('/workflows/{id}/instances', [WorkflowController::class, 'createInstance']);

    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::post('/integrations/{provider}/connect', [IntegrationController::class, 'connect']);
    Route::post('/integrations/{id}/sync', [IntegrationController::class, 'sync']);
    Route::get('/integrations/{id}/health', [IntegrationController::class, 'health']);

    Route::get('/events', [EventLogController::class, 'index']);
    Route::post('/webhooks/replay/{id}', [WebhookReplayController::class, 'replay']);

    Route::get('/ops/failed-jobs', [OpsController::class, 'failedJobs']);
    Route::post('/ops/failed-jobs/{uuid}/retry', [OpsController::class, 'retryFailedJob']);
});

// Partner-facing API-key protected routes.
Route::prefix('v1')
    ->middleware(['throttle:tenant-api', 'auth.apikey:*'])
    ->group(function () {
        Route::get('/partner/events', [EventLogController::class, 'index']);
        Route::post('/partner/integrations/{id}/sync', [IntegrationController::class, 'sync']);
        Route::post('/partner/webhooks/replay/{id}', [WebhookReplayController::class, 'replay']);
    });
