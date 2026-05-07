<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Uploads;
use App\Services\CapsSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs CAPS verification and preview for a newly uploaded submission.
 *
 * Dispatched as a queued job so the upload HTTP response returns instantly.
 * With QUEUE_CONNECTION=sync this still runs in-process but with an
 * extended execution time limit so CAPS API calls don't timeout.
 */
class ProcessCapsUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 2;

    public function __construct(
        private int $uploadId,
        private int $companyId,
        private bool $hasSystemsImport,
    ) {}

    public function handle(): void
    {
        // Extend execution time for sync queue driver
        if (function_exists('set_time_limit')) {
            set_time_limit(120);
        }

        $upload = Uploads::find($this->uploadId);
        $company = Company::find($this->companyId);
        if (!$upload || !$company) return;

        // CAPS member/policy verification
        try {
            app(\App\Http\Controllers\UploadsController::class)
                ->runAutoVerification($upload, $company);
        } catch (\Throwable $e) {
            Log::warning('CAPS auto-verification failed for upload #' . $this->uploadId . ': ' . $e->getMessage());
        }

        // CAPS premium preview
        if ($this->hasSystemsImport) {
            try {
                app(CapsSubmissionService::class)->previewOnCaps($upload);
            } catch (\Throwable $e) {
                Log::warning('CAPS auto-preview failed for upload #' . $this->uploadId . ': ' . $e->getMessage());
            }
        }
    }
}
