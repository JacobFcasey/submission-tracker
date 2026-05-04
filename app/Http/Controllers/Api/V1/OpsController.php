<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class OpsController extends Controller
{
    public function failedJobs(): JsonResponse
    {
        $rows = DB::table('failed_jobs')
            ->select(['uuid', 'connection', 'queue', 'failed_at', 'exception'])
            ->orderByDesc('failed_at')
            ->limit(100)
            ->get();

        return response()->json($rows);
    }

    public function retryFailedJob(string $uuid): JsonResponse
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return response()->json([
            'message' => 'Retry requested.',
            'uuid' => $uuid,
        ]);
    }
}

