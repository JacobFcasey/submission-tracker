<?php

namespace App\Console\Commands;

use App\Services\CaseyReferenceDataService;
use Illuminate\Console\Command;

class SyncCaseyReferenceData extends Command
{
    /**
     * Sync canonical Company / Municipality master data from CAPS.
     *
     * Usage:
     *   php artisan casey:sync-reference-data                   (sync both)
     *   php artisan casey:sync-reference-data --only=companies
     *   php artisan casey:sync-reference-data --only=municipalities
     */
    protected $signature = 'casey:sync-reference-data
        {--only= : Restrict the sync to "companies" or "municipalities"}';

    protected $description = 'Pull the authoritative Company / Municipality lists from CAPS into the Submission Tracker.';

    public function handle(CaseyReferenceDataService $service): int
    {
        $only = $this->option('only');

        $municipalities = null;
        $companies = null;

        if ($only === null || $only === 'municipalities') {
            $this->info('Syncing municipalities from CAPS...');
            $municipalities = $service->syncMunicipalities();
            $this->renderResult('Municipalities', $municipalities);
        }

        if ($only === null || $only === 'companies') {
            $this->info('Syncing companies from CAPS...');
            $companies = $service->syncCompanies();
            $this->renderResult('Companies', $companies);
        }

        $allOk = collect([$municipalities, $companies])
            ->filter()
            ->every(fn ($r) => $r['ok'] === true);

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    private function renderResult(string $label, array $result): void
    {
        if (! ($result['ok'] ?? false)) {
            $this->error("$label sync failed: " . ($result['message'] ?? 'unknown error'));
            return;
        }

        $this->line(sprintf(
            '  %s: fetched=%d, created=%d, updated=%d, skipped=%d',
            $label,
            $result['fetched'] ?? 0,
            $result['created'] ?? 0,
            $result['updated'] ?? 0,
            $result['skipped'] ?? 0,
        ));
    }
}
