<?php

namespace App\Services;

use App\Models\Uploads;
use App\Models\Company;
use App\Models\Municipality;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * CAPS Premium Batch Upload — Full 6-Phase Flow.
 *
 * Phase 1: Preview   — POST /v1/premiums/preview  → validate headers
 * Phase 2: Import    — POST /v1/premiums/import    → stage records, get batchId
 * Phase 3: Review    — GET  /v1/premiums/batch/stage/detailed_info → staged records by category
 * Phase 4: Save      — POST /v1/premiums/save      → finalize batch
 * Phase 5: Batch Info— GET  /v1/premiums/batch/detailed_info → final results
 *
 * All records are also read locally from the file so the UI always has complete data.
 */
class CapsSubmissionService
{
    // ── Phase 1: Preview (validate headers) ─────────────────────────────

    public function preview(Uploads $upload): array
    {
        [$fileContents, $fileName, $company] = $this->resolveFile($upload);
        if (!$fileContents) return $this->fail($upload, $fileName); // $fileName holds error msg

        try {
            $client = $this->buildAuthenticatedClient();
            $response = $client
                ->attach('file', $fileContents, $fileName)
                ->post($this->url('/v1/premiums/preview'));

            if ($response->failed()) {
                return $this->fail($upload, 'CAPS preview returned HTTP ' . $response->status());
            }

            $data = $response->json();
            $localData = $this->parseFileLocally($fileContents, $fileName);

            // Company name guard: check file rows match the upload company
            $companyWarnings = $this->checkCompanyNameMatch($upload, $localData['rows']);

            $summary = [
                'phase' => 'previewed',
                'total' => count($localData['rows']),
                'given_headers' => $data['givenHeaders'] ?? $localData['headers'],
                'matched_headers' => $data['matchHeaders'] ?? [],
                'unmatched_headers' => $data['unMatchedMandatoryHeaders'] ?? [],
                'unmatched_optional' => $data['unMatchedOptionalHeaders'] ?? [],
                'can_submit' => $data['canSubmit'] ?? true,
                'total_premium' => $this->sumPremium($localData['rows']),
                'status_counts' => $this->countStatuses($localData['rows']),
                'records' => $localData['rows'],
                'company_warnings' => $companyWarnings,
            ];

            $upload->update([
                'caps_dispatch_status' => Uploads::DISPATCH_DISPATCHED,
                'caps_dispatched_at' => now(),
                'caps_batch_type' => 'premium_import',
                'caps_status' => 'previewed',
                'caps_summary' => $summary,
                'caps_errors' => null,
            ]);

            return ['ok' => true, 'phase' => 'previewed', 'summary' => $summary];
        } catch (\Throwable $e) {
            Log::error('CAPS preview failed', ['upload_id' => $upload->id, 'error' => $e->getMessage()]);
            return $this->fail($upload, $e->getMessage());
        }
    }

    // ── Phase 2: Import (stage records in CAPS, get batchId) ────────────

    public function import(Uploads $upload): array
    {
        [$fileContents, $fileName, $company] = $this->resolveFile($upload);
        if (!$fileContents) return $this->fail($upload, $fileName);

        try {
            $client = $this->buildAuthenticatedClient();
            // Don't send organizationId — let CAPS match from the Company Name
            // column in the file. Sending it forces all rows to match that one
            // company and rejects everything else as a mismatch.
            $response = $client
                ->attach('file', $fileContents, $fileName)
                ->post($this->url('/v1/premiums/import'));

            if ($response->failed()) {
                return $this->fail($upload, 'CAPS import returned HTTP ' . $response->status());
            }

            $data = $response->json();
            $batchId = $data['policyBatchId'] ?? $data['batchId'] ?? null;

            if (!$batchId || ($data['status'] ?? '') !== 'SUCCESS') {
                return $this->fail($upload, $data['msg'] ?? 'CAPS import failed — no batch ID returned.');
            }

            // Fetch batch info from CAPS to get categorized counts
            $batchInfo = $this->fetchBatchInfo($batchId);

            $localData = $this->parseFileLocally($fileContents, $fileName);

            $summary = $upload->caps_summary ?? [];
            $summary['phase'] = 'imported';
            $summary['caps_batch_id'] = $batchId;
            $summary['total'] = count($localData['rows']);
            $summary['total_premium'] = $this->sumPremium($localData['rows']);
            $summary['status_counts'] = $this->countStatuses($localData['rows']);
            $summary['records'] = $localData['rows'];

            // Merge CAPS batch counts (exact field names from /batch/info)
            if ($batchInfo) {
                $summary['caps_new'] = $batchInfo['newRecords'] ?? 0;
                $summary['caps_updated'] = $batchInfo['updatedRecords'] ?? 0;
                $summary['caps_cancelled'] = $batchInfo['cancelledRecords'] ?? 0;
                $summary['caps_errors'] = $batchInfo['errors'] ?? 0;
                $summary['caps_inactive_members'] = $batchInfo['inactiveMembers'] ?? 0;
                $summary['caps_inactive_policies'] = $batchInfo['inactivePolicies'] ?? 0;
                $summary['caps_duplicates'] = $batchInfo['duplicatePolicies'] ?? 0;
                $summary['caps_unaffordable'] = $batchInfo['affordability'] ?? 0;
                $summary['caps_total'] = $batchInfo['totalRecords'] ?? 0;
                $summary['caps_file_name'] = $batchInfo['fileName'] ?? null;
                $summary['caps_user'] = $batchInfo['userName'] ?? null;

                // Fetch categorized records from CAPS staged data
                // CAPS criteria values match the frontend tab names exactly
                $categories = [
                    'new' => ['criteria' => 'newPolicies', 'count' => $batchInfo['newRecords'] ?? 0],
                    'updated' => ['criteria' => 'updatePolicies', 'count' => $batchInfo['updatedRecords'] ?? 0],
                    'cancelled' => ['criteria' => 'cancelledPolicies', 'count' => $batchInfo['cancelledRecords'] ?? 0],
                    'errors' => ['criteria' => 'errors', 'count' => $batchInfo['errors'] ?? 0],
                    'inactive_members' => ['criteria' => 'inactiveMembers', 'count' => $batchInfo['inactiveMembers'] ?? 0],
                    'inactive_policies' => ['criteria' => 'inactivePolicies', 'count' => $batchInfo['inactivePolicies'] ?? 0],
                    'duplicates' => ['criteria' => 'duplicatePolicies', 'count' => $batchInfo['duplicatePolicies'] ?? 0],
                    'affordability' => ['criteria' => 'affordability', 'count' => $batchInfo['affordability'] ?? 0],
                ];

                foreach ($categories as $key => $cat) {
                    if ($cat['count'] > 0) {
                        $raw = $this->fetchStagedRecords($batchId, $cat['criteria']);
                        $summary[$key . '_records'] = array_map(fn($r) => [
                            'row' => $r['rowNumber'] ?? null,
                            'member_id' => $r['memberId'] ?? null,
                            'employee_no' => $r['personelNumber'] ?? null,
                            'first_name' => $r['firstName'] ?? null,
                            'surname' => $r['surName'] ?? null,
                            'policy_code' => $r['policyCode'] ?? null,
                            'company' => $r['organisationName'] ?? null,
                            'premium' => $r['premiumAmount'] ?? null,
                            'status' => $r['policyStatus'] ?? null,
                            'error' => $r['errorMsg'] ?? null,
                            'note' => $r['note'] ?? null,
                            'derived_status' => $r['derivedStatus'] ?? null,
                        ], $raw);
                    }
                }

                // Keep backward compat alias
                $summary['error_records'] = $summary['errors_records'] ?? [];
            }

            $upload->update([
                'caps_dispatch_status' => Uploads::DISPATCH_CAPS_PROCESSING,
                'caps_payment_batch_id' => (string) $batchId,
                'caps_status' => 'imported',
                'caps_summary' => $summary,
                'caps_errors' => null,
            ]);

            Log::info('CAPS import successful', ['upload_id' => $upload->id, 'batch_id' => $batchId]);

            return ['ok' => true, 'phase' => 'imported', 'batch_id' => $batchId, 'summary' => $summary];
        } catch (\Throwable $e) {
            Log::error('CAPS import failed', ['upload_id' => $upload->id, 'error' => $e->getMessage()]);
            return $this->fail($upload, $e->getMessage());
        }
    }

    // ── Phase 4: Save (finalize batch in CAPS) ──────────────────────────

    public function save(Uploads $upload): array
    {
        $batchId = $upload->caps_payment_batch_id;
        if (!$batchId) {
            return $this->fail($upload, 'No CAPS batch ID — run import first.');
        }

        try {
            $client = $this->buildAuthenticatedClient();
            $response = $client->post($this->url('/v1/premiums/save'), [
                'stagingPolicyBatchId' => (int) $batchId,
            ]);

            if ($response->failed()) {
                return $this->fail($upload, 'CAPS save returned HTTP ' . $response->status());
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'SUCCESS') {
                return $this->fail($upload, $data['msg'] ?? 'CAPS save failed.');
            }

            $summary = $upload->caps_summary ?? [];
            $summary['phase'] = 'saved';

            $upload->update([
                'caps_dispatch_status' => Uploads::DISPATCH_COMPLETED,
                'caps_status' => 'saved',
                'caps_summary' => $summary,
            ]);

            Log::info('CAPS save successful', ['upload_id' => $upload->id, 'batch_id' => $batchId]);

            return ['ok' => true, 'phase' => 'saved', 'batch_id' => $batchId, 'summary' => $summary];
        } catch (\Throwable $e) {
            Log::error('CAPS save failed', ['upload_id' => $upload->id, 'error' => $e->getMessage()]);
            return $this->fail($upload, $e->getMessage());
        }
    }

    // ── Full dispatch: Preview → Import (2 phases in one call) ──────────

    public function previewOnCaps(Uploads $upload): array
    {
        // Phase 1: Preview
        $previewResult = $this->preview($upload);
        if (!$previewResult['ok']) return $previewResult;

        // If headers are valid, auto-proceed to Phase 2: Import
        $canSubmit = $previewResult['summary']['can_submit'] ?? false;
        if (!$canSubmit) {
            return $previewResult; // Stay at preview — user needs to fix headers
        }

        // Phase 2: Import
        return $this->import($upload);
    }

    // ── Retry ───────────────────────────────────────────────────────────

    public function retry(Uploads $upload): array
    {
        $upload->update([
            'caps_dispatch_status' => Uploads::DISPATCH_DRAFT,
            'caps_retry_count' => ($upload->caps_retry_count ?? 0) + 1,
            'caps_last_retry_at' => now(),
            'caps_errors' => null,
            'caps_payment_batch_id' => null,
        ]);

        return $this->previewOnCaps($upload);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function resolveFile(Uploads $upload): array
    {
        $company = Company::find($upload->company_id);
        if (!$company || !$company->casey_id) {
            return [null, 'Company is not linked to CAPS.', null];
        }

        $filePath = $upload->systems_import_file_path;
        $fileName = $upload->systems_import_file_name;
        if (!$filePath || !Storage::disk('private')->exists($filePath)) {
            $filePath = $upload->workings_file_path;
            $fileName = $upload->workings_file_name;
        }
        if (!$filePath || !Storage::disk('private')->exists($filePath)) {
            return [null, 'No import file found.', null];
        }

        return [Storage::disk('private')->get($filePath), $fileName ?? basename($filePath), $company];
    }

    private function fetchBatchInfo(int|string $batchId): ?array
    {
        try {
            $client = $this->buildAuthenticatedClient();
            $response = $client->get($this->url('/v1/premiums/batch/info'), [
                'policyBatchId' => $batchId,
            ]);
            return $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            Log::warning('CAPS batch info fetch failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch staged records from CAPS by criteria (errors, new, updated, cancelled, etc.)
     * Each record includes errorMsg for errors.
     */
    private function fetchStagedRecords(int|string $batchId, string $criteria, int $maxPages = 10): array
    {
        $all = [];
        $page = 0;
        $size = 200;

        try {
            $client = $this->buildAuthenticatedClient();

            do {
                $response = $client->get($this->url('/v1/premiums/batch/stage/detailed_info'), [
                    'policyBatchId' => $batchId,
                    'criteria' => $criteria,
                    'page' => $page,
                    'size' => $size,
                ]);

                if ($response->failed()) break;

                $data = $response->json();
                $content = $data['policies']['content'] ?? [];
                if (empty($content)) break;

                $all = array_merge($all, $content);
                $totalPages = $data['policies']['totalPages'] ?? 1;
                $page++;
            } while ($page < $totalPages && $page < $maxPages);
        } catch (\Throwable $e) {
            Log::warning("CAPS stage fetch ($criteria) failed: " . $e->getMessage());
        }

        return $all;
    }

    private function fail(Uploads $upload, string $message, ?array $body = null): array
    {
        $errors = $body['errors'] ?? [['message' => $message]];
        $upload->update([
            'caps_dispatch_status' => Uploads::DISPATCH_FAILED,
            'caps_errors' => $errors,
        ]);
        return ['ok' => false, 'summary' => null, 'message' => $message];
    }

    private function parseFileLocally(string $contents, string $fileName): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return in_array($ext, ['xlsx', 'xls', 'xlsm'])
            ? $this->parseExcelContents($contents, $fileName)
            : $this->parseCsvContents($contents);
    }

    private function parseCsvContents(string $contents): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", $contents)), fn($l) => trim($l) !== '');
        if (empty($lines)) return ['headers' => [], 'rows' => []];

        $headers = array_map('trim', str_getcsv(array_shift($lines), ',', '"', ''));
        $rows = [];
        foreach ($lines as $line) {
            $cells = str_getcsv($line, ',', '"', '');
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = trim($cells[$i] ?? '');
            }
            $rows[] = $row;
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    private function parseExcelContents(string $contents, string $fileName): array
    {
        $tmpPath = sys_get_temp_dir() . '/' . uniqid('caps_') . '_' . $fileName;
        file_put_contents($tmpPath, $contents);
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmpPath);
            $data = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            if (empty($data)) return ['headers' => [], 'rows' => []];

            $headers = array_map(fn($h) => trim((string)($h ?? '')), array_shift($data));
            $rows = [];
            foreach ($data as $cells) {
                $row = [];
                foreach ($headers as $i => $h) {
                    if ($h === '') continue;
                    $row[$h] = trim((string)($cells[$i] ?? ''));
                }
                if (implode('', array_values($row)) === '') continue;
                $rows[] = $row;
            }
            return ['headers' => array_filter($headers, fn($h) => $h !== ''), 'rows' => $rows];
        } finally {
            @unlink($tmpPath);
        }
    }

    private function sumPremium(array $rows): float
    {
        $total = 0;
        foreach ($rows as $r) {
            $amount = $r['Premium Amount'] ?? $r['Premium'] ?? $r['Amount Payable'] ?? $r['premium_amount'] ?? 0;
            $total += (float) $amount;
        }
        return round($total, 2);
    }

    private function countStatuses(array $rows): array
    {
        $counts = ['new' => 0, 'updated' => 0, 'cancelled' => 0, 'other' => 0];
        foreach ($rows as $r) {
            $s = $r['Policy Status'] ?? $r['Status'] ?? $r['status'] ?? '';
            match ((string) $s) {
                '1', 'New', 'new' => $counts['new']++,
                '2', 'Updated', 'updated', 'Update' => $counts['updated']++,
                '0', 'Cancelled', 'cancelled', 'Cancel' => $counts['cancelled']++,
                default => $counts['other']++,
            };
        }
        return $counts;
    }

    /**
     * Check company name mismatch BEFORE dispatch. Returns error string or null.
     */
    public function getCompanyMismatch(Uploads $upload): ?string
    {
        [$fileContents, $fileName, $company] = $this->resolveFile($upload);
        if (!$fileContents || !$company) return null;

        $localData = $this->parseFileLocally($fileContents, $fileName);
        $rows = $localData['rows'];
        if (empty($rows)) return null;

        $expectedName = strtolower(trim($company->name));
        $fileCompanies = [];
        foreach ($rows as $r) {
            $name = trim($r['Company Name'] ?? $r['company_name'] ?? $r['Company'] ?? '');
            if ($name !== '') $fileCompanies[strtolower($name)] = $name;
        }

        if (empty($fileCompanies)) return null;

        // ALL rows must match the selected company — any mismatch blocks dispatch
        $mismatched = [];
        foreach ($fileCompanies as $lower => $original) {
            if ($lower !== $expectedName) {
                $mismatched[] = $original;
            }
        }

        if (!empty($mismatched)) {
            $found = implode(', ', $mismatched);
            return "Company name mismatch: You are uploading for \"{$company->name}\" but the file contains records for \"{$found}\". All rows must match the selected company. Please correct the file or select the right company.";
        }

        return null;
    }

    /**
     * Check if Company Name values in the file match the upload's company.
     * Returns warnings for any mismatches (doesn't block, just warns).
     */
    private function checkCompanyNameMatch(Uploads $upload, array $rows): array
    {
        $expectedCompany = $upload->company?->name;
        if (!$expectedCompany || empty($rows)) return [];

        $companyNames = [];
        foreach ($rows as $r) {
            $name = trim($r['Company Name'] ?? $r['company_name'] ?? $r['Company'] ?? '');
            if ($name !== '') $companyNames[$name] = ($companyNames[$name] ?? 0) + 1;
        }

        $warnings = [];
        $expectedLower = strtolower($expectedCompany);
        foreach ($companyNames as $name => $count) {
            if (strtolower($name) !== $expectedLower) {
                $warnings[] = "File contains {$count} row(s) for \"{$name}\" but upload is for \"{$expectedCompany}\".";
            }
        }

        return $warnings;
    }

    // ── Auth & HTTP ─────────────────────────────────────────────────────

    private function buildAuthenticatedClient(): \Illuminate\Http\Client\PendingRequest
    {
        $verifySsl = (bool) config('services.casey.verify_ssl', true);
        $client = Http::timeout(60)->withOptions(['verify' => $verifySsl]);

        $jwt = session('caps_jwt');
        if ($jwt) return $client->withToken($jwt);

        $token = $this->getServiceAccountToken();
        if ($token) return $client->withToken($token);

        return $client->withBasicAuth(
            (string) config('services.casey.username', ''),
            (string) config('services.casey.password', '')
        );
    }

    private function getServiceAccountToken(): ?string
    {
        return Cache::remember('caps_service_token', 50 * 60, function () {
            $baseUrl = rtrim((string) config('services.casey.base_url', ''), '/');
            $authEndpoint = ltrim((string) config('services.casey.auth_endpoint', ''), '/');
            if (!$baseUrl || !$authEndpoint) return null;

            try {
                $response = Http::withOptions(['verify' => (bool) config('services.casey.verify_ssl', true)])
                    ->post("$baseUrl/$authEndpoint", [
                        'username' => (string) config('services.casey.username', ''),
                        'password' => (string) config('services.casey.password', ''),
                    ]);
                return $response->successful() ? ($response->json('token') ?? $response->json('access_token')) : null;
            } catch (\Throwable $e) {
                Log::warning('CAPS auth failed', ['error' => $e->getMessage()]);
                return null;
            }
        });
    }

    private function url(string $endpoint): string
    {
        return rtrim((string) config('services.casey.base_url', ''), '/') . '/' . ltrim($endpoint, '/');
    }
}
