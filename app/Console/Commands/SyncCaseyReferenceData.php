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
        {--only= : Restrict the sync to "companies", "municipalities", "members", or "policies"}
        {--include-members : Also sync members from CAPS}
        {--include-policies : Also sync policies from CAPS}';

    protected $description = 'Pull authoritative data from CAPS into the Submission Tracker (municipalities, companies, members, policies).';

    public function handle(CaseyReferenceDataService $service): int
    {
        $only = $this->option('only');
        $includeMembers = $this->option('include-members');
        $includePolicies = $this->option('include-policies');

        $results = [];

        if ($only === null || $only === 'municipalities') {
            $this->info('Syncing municipalities from CAPS...');
            $results['municipalities'] = $service->syncMunicipalities();
            $this->renderResult('Municipalities', $results['municipalities']);
        }

        if ($only === null || $only === 'companies') {
            $this->info('Syncing companies from CAPS...');
            $results['companies'] = $service->syncCompanies();
            $this->renderResult('Companies', $results['companies']);
        }

        if ($only === 'members' || $includeMembers) {
            $this->info('Syncing members from CAPS...');
            $results['members'] = $service->syncMembers();
            $this->renderResult('Members', $results['members']);
        }

        if ($only === 'policies' || $includePolicies) {
            $this->info('Syncing policies from CAPS...');
            $results['policies'] = $service->syncPolicies();
            $this->renderResult('Policies', $results['policies']);
        }

        $allOk = collect($results)
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
