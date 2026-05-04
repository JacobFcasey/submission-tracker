<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pulls member and policy data from CAPS for comparison against uploaded files.
 *
 * Uses the same authentication pattern as CaseyPremiumBatchService:
 * logged-in user's SSO JWT first, falling back to service account credentials.
 */
class CaseyMemberPolicyService
{
    /**
     * Look up a single member by their SA ID number (exact match).
     * The CAPS members API supports `?idNumber=X` for precise filtering.
     */
    public function fetchMemberByIdNumber(string $idNumber): array
    {
        return $this->callCaps(
            config('services.casey.members_endpoint', '/v1/member/api/members'),
            ['idNumber' => $idNumber, 'page' => 0, 'size' => 5],
            'member_lookup'
        );
    }

    /**
     * Fetch policies for a specific deduction company using `organizationId`.
     * This is the correct filter — `companyId` does NOT filter in CAPS.
     */
    public function fetchPoliciesByOrganization(string $organizationId, int $page = 0, int $size = 500): array
    {
        return $this->callCaps(
            config('services.casey.policies_endpoint', '/v1/premiums/status/fetch'),
            ['organizationId' => $organizationId, 'page' => $page, 'size' => $size],
            'policies'
        );
    }

    /**
     * Look up members by their SA ID numbers. Makes one API call per unique
     * ID using the `idNumber` filter (exact match, returns 0 or 1 result).
     */
    public function fetchMembersByIdNumbers(array $idNumbers): array
    {
        $allMembers = [];

        foreach (array_unique($idNumbers) as $idNumber) {
            $idNumber = trim($idNumber);
            if ($idNumber === '') continue;

            $result = $this->fetchMemberByIdNumber($idNumber);
            if ($result['ok']) {
                $content = data_get($result, 'data.content', []);
                if (is_array($content)) {
                    foreach ($content as $m) {
                        if (is_array($m)) $allMembers[] = $m;
                    }
                }
            }
        }

        return [
            'ok' => true,
            'data' => ['content' => $allMembers],
            'total' => count($allMembers),
        ];
    }

    /**
     * Compare uploaded spreadsheet rows against CAPS.
     *
     * Rows may contain a `companyName` field — when present, each row is
     * verified against THAT company in CAPS (resolved by fuzzy name match).
     * When absent, $fallbackCompanyId is used for all rows.
     *
     * @param array  $uploadedRows       Rows with: memberId, personelNumber, policyCode, premiumAmount, companyName?
     * @param string $fallbackCompanyId  casey_id used when a row has no companyName
     * @return array  Comparison results
     */
    public function compareAgainstCaps(array $uploadedRows, string $fallbackCompanyId): array
    {
        // ── Group rows by company ──
        $rowsByCompany = [];
        foreach ($uploadedRows as $row) {
            $companyName = trim($row['companyName'] ?? '');
            $key = $companyName !== '' ? $companyName : '__fallback__';
            $rowsByCompany[$key][] = $row;
        }

        // ── Resolve each company name to a casey_id ──
        $companyIdMap = [];
        foreach (array_keys($rowsByCompany) as $name) {
            if ($name === '__fallback__') {
                $companyIdMap[$name] = $fallbackCompanyId;
                continue;
            }
            $resolved = $this->resolveCompanyCaseyId($name);
            $companyIdMap[$name] = $resolved ?? $fallbackCompanyId;

            if ($resolved) {
                Log::info("CAPS verify: resolved company '{$name}' to casey_id {$resolved}");
            } else {
                Log::warning("CAPS verify: could not resolve company '{$name}', using fallback {$fallbackCompanyId}");
            }
        }

        // ── Fetch CAPS data per company (cached to avoid duplicate calls) ──
        $membersByCompany = [];
        $policiesByCompany = [];
        $fetchedCompanies = [];

        foreach (array_unique(array_values($companyIdMap)) as $caseyId) {
            if (isset($fetchedCompanies[$caseyId])) continue;

            // Collect unique SA ID numbers for this company's rows
            $idNumbers = [];
            foreach ($rowsByCompany as $name => $rows) {
                if (($companyIdMap[$name] ?? '') !== $caseyId) continue;
                foreach ($rows as $row) {
                    $mid = trim($row['memberId'] ?? '');
                    if ($mid !== '') $idNumbers[$mid] = true;
                }
            }

            // Members: exact lookup by idNumber (1 API call per unique ID)
            $membersResult = $this->fetchMembersByIdNumbers(array_keys($idNumbers));
            // Policies: filtered by organizationId (correct CAPS param)
            $policiesResult = $this->fetchAllPoliciesForCompany($caseyId);

            $membersByCompany[$caseyId] = $this->indexMembers($membersResult);
            $policiesByCompany[$caseyId] = $this->indexPolicies($policiesResult);
            $fetchedCompanies[$caseyId] = [
                'members_ok' => $membersResult['ok'],
                'policies_ok' => $policiesResult['ok'],
                'members_error' => $membersResult['ok'] ? null : ($membersResult['message'] ?? 'Failed to fetch'),
                'policies_error' => $policiesResult['ok'] ? null : ($policiesResult['message'] ?? 'Failed to fetch'),
            ];

            Log::info("CAPS verify data indexed for {$caseyId}", [
                'members' => count($membersByCompany[$caseyId]),
                'policies' => count($policiesByCompany[$caseyId]),
            ]);
        }

        // ── Compare each row against its company's CAPS data ──
        $results = [
            'member_found' => [],
            'member_not_found' => [],
            'policy_found' => [],
            'policy_not_found' => [],
            'premium_mismatch' => [],
            'caps_members_total' => array_sum(array_map('count', $membersByCompany)),
            'caps_policies_total' => array_sum(array_map('count', $policiesByCompany)),
            'uploaded_rows_total' => count($uploadedRows),
            'caps_members_error' => null,
            'caps_policies_error' => null,
            'companies_resolved' => count($fetchedCompanies),
        ];

        // Collect any errors
        foreach ($fetchedCompanies as $info) {
            if ($info['members_error']) $results['caps_members_error'] = $info['members_error'];
            if ($info['policies_error']) $results['caps_policies_error'] = $info['policies_error'];
        }

        foreach ($uploadedRows as $row) {
            $companyName = trim($row['companyName'] ?? '');
            $key = $companyName !== '' ? $companyName : '__fallback__';
            $caseyId = $companyIdMap[$key] ?? $fallbackCompanyId;

            $capsMembers = $membersByCompany[$caseyId] ?? [];
            $capsPolicies = $policiesByCompany[$caseyId] ?? [];

            $memberId = strtolower(trim($row['memberId'] ?? ''));
            $personnelNo = strtolower(trim($row['personelNumber'] ?? $row['personnelNumber'] ?? ''));
            $policyCode = strtolower(trim($row['policyCode'] ?? ''));
            $premiumAmount = $row['premiumAmount'] ?? null;

            // ── Member existence ──
            $memberFound = false;
            if ($memberId !== '' && isset($capsMembers[$memberId])) {
                $memberFound = true;
            } elseif ($personnelNo !== '' && isset($capsMembers[$personnelNo])) {
                $memberFound = true;
            }

            if ($memberId !== '' || $personnelNo !== '') {
                if ($memberFound) {
                    $results['member_found'][] = $row;
                } else {
                    $results['member_not_found'][] = $row;
                }
            }

            // ── Policy existence + premium match ──
            if ($policyCode !== '') {
                if (isset($capsPolicies[$policyCode])) {
                    $results['policy_found'][] = $row;

                    $capsPolicy = $capsPolicies[$policyCode];
                    $capsPremium = (float) ($capsPolicy['premiumAmount']
                        ?? $capsPolicy['totalAmount']
                        ?? $capsPolicy['amount'] ?? 0);
                    $uploadPremium = (float) ($premiumAmount ?? 0);

                    if (($uploadPremium > 0 || $capsPremium > 0)
                        && abs($capsPremium - $uploadPremium) > 0.01) {
                        $results['premium_mismatch'][] = array_merge($row, [
                            'caps_premium' => $capsPremium,
                            'uploaded_premium' => $uploadPremium,
                        ]);
                    }
                } else {
                    $results['policy_not_found'][] = $row;
                }
            }
        }

        return $results;
    }

    /**
     * Resolve a company name from a spreadsheet to its casey_id in the local DB.
     * Uses fuzzy matching: exact name first, then LIKE with the first significant words.
     */
    private function resolveCompanyCaseyId(string $name): ?string
    {
        if ($name === '') return null;

        // Exact match
        $company = \App\Models\Company::where('name', $name)->first();
        if ($company?->casey_id) return $company->casey_id;

        // Try without trailing suffixes like " - Regent", " - Group", etc.
        $baseName = preg_replace('/\s*-\s*[^-]+$/', '', $name);
        if ($baseName !== $name) {
            $company = \App\Models\Company::where('name', 'LIKE', $baseName . '%')->first();
            if ($company?->casey_id) return $company->casey_id;
        }

        // LIKE contains match
        $company = \App\Models\Company::where('name', 'LIKE', '%' . $name . '%')->first();
        if ($company?->casey_id) return $company->casey_id;

        // Try the first 3 significant words
        $words = preg_split('/\s+/', $name);
        $significantWords = array_filter($words, fn($w) => strlen($w) > 2);
        $prefix = implode(' ', array_slice($significantWords, 0, 3));
        if (strlen($prefix) > 5) {
            $company = \App\Models\Company::where('name', 'LIKE', '%' . $prefix . '%')->first();
            if ($company?->casey_id) return $company->casey_id;
        }

        return null;
    }

    /**
     * Fetch ALL policies for a deduction company using the `organizationId`
     * filter (the correct CAPS parameter). Paginates through the full result set.
     */
    public function fetchAllPoliciesForCompany(string $companyId): array
    {
        $allPolicies = [];
        $page = 0;
        $pageSize = 500;
        $maxPages = 60; // safety: 30K policies max
        $totalElements = 0;

        do {
            $result = $this->fetchPoliciesByOrganization($companyId, $page, $pageSize);

            if (!$result['ok']) {
                if (!empty($allPolicies)) break;
                return $result;
            }

            $content = data_get($result, 'data.policyStatuses.content',
                data_get($result, 'data.content', []));
            if (!is_array($content)) $content = [];

            foreach ($content as $p) {
                if (is_array($p)) $allPolicies[] = $p;
            }

            // Get total from first page response
            if ($page === 0) {
                $totalElements = (int) data_get($result, 'data.policyStatuses.totalElements',
                    data_get($result, 'data.totalElements', 0));
            }

            $page++;

            // Only stop when we have ALL records or hit an empty page
            if (empty($content) || ($totalElements > 0 && count($allPolicies) >= $totalElements)) break;
        } while ($page < $maxPages);

        Log::info("CAPS policies fetched for org {$companyId}: {$page} pages, " . count($allPolicies) . "/{$totalElements} policies");

        return [
            'ok' => true,
            'data' => ['content' => $allPolicies],
            'total' => count($allPolicies),
        ];
    }

    /**
     * Build a lowercase-keyed index of CAPS members.
     *
     * CAPS member records use:
     *   - `idNumber`  = SA ID number (the primary human identifier)
     *   - `payNumber`  = employee/personnel number
     *   - `id`        = CAPS internal UUID (NOT used for matching)
     *
     * NOTE: `memberId` does NOT exist on member records — that field only
     * appears on POLICY records (where it stores the SA ID).
     */
    private function indexMembers(array $membersResult): array
    {
        $index = [];

        if (!$membersResult['ok']) return $index;

        $content = data_get($membersResult, 'data.content', $membersResult['data'] ?? []);
        if (!is_array($content)) return $index;

        foreach ($content as $m) {
            if (!is_array($m)) continue;

            // Index by human-facing identifiers only (not CAPS internal UUID)
            $keys = [
                $m['idNumber'] ?? null,       // SA ID number
                $m['payNumber'] ?? null,       // personnel/pay number
                $m['personnelNumber'] ?? null, // alternate personnel number field
            ];

            foreach ($keys as $raw) {
                if ($raw === null) continue;
                $key = strtolower(trim((string) $raw));
                if ($key !== '' && !isset($index[$key])) {
                    $index[$key] = $m;
                }
            }
        }

        return $index;
    }

    /**
     * Build a lowercase-keyed index of CAPS policies.
     * On duplicate codes, keeps the entry with the highest premium.
     */
    private function indexPolicies(array $policiesResult): array
    {
        $index = [];

        if (!$policiesResult['ok']) return $index;

        $content = data_get($policiesResult, 'data.content', $policiesResult['data'] ?? []);
        if (!is_array($content)) return $index;

        foreach ($content as $p) {
            if (!is_array($p)) continue;

            $code = strtolower(trim($p['policyCode'] ?? $p['policyNumber'] ?? $p['policyNo'] ?? ''));
            if ($code === '') continue;

            $newPremium = (float) ($p['premiumAmount'] ?? $p['totalAmount'] ?? $p['amount'] ?? 0);

            if (isset($index[$code])) {
                $existingPremium = (float) ($index[$code]['premiumAmount']
                    ?? $index[$code]['totalAmount'] ?? $index[$code]['amount'] ?? 0);
                if ($newPremium > $existingPremium) {
                    $index[$code] = $p;
                }
            } else {
                $index[$code] = $p;
            }
        }

        return $index;
    }

    /**
     * Generic CAPS API call with auth resolution.
     */
    private function callCaps(string $endpoint, array $params, string $context): array
    {
        $baseUrl = trim((string) config('services.casey.base_url', ''));
        $authEndpoint = trim((string) config('services.casey.auth_endpoint', '/casey/auth/sign-in'));
        $username = (string) config('services.casey.username', '');
        $password = (string) config('services.casey.password', '');
        $verifySsl = (bool) config('services.casey.verify_ssl', true);

        if ($baseUrl === '' || $username === '' || $password === '') {
            return [
                'ok' => false,
                'message' => 'CAPS API credentials or base URL are missing.',
                'data' => [],
            ];
        }

        $requestUrl = $this->buildRequestUrl($baseUrl, $endpoint);
        $authUrl = $this->buildRequestUrl($baseUrl, $authEndpoint);

        try {
            $client = Http::timeout(30)
                ->connectTimeout(10)
                ->retry(2, 500, null, false)
                ->acceptJson();

            if (!$verifySsl) {
                $client = $client->withoutVerifying();
            }

            $token = $this->resolveAccessToken($authUrl, $username, $password, $verifySsl);
            if ($token) {
                $client = $client->withToken($token);
            } else {
                $client = $client->withBasicAuth($username, $password);
            }

            $response = $client->get($requestUrl, $params);

            if ($response->failed()) {
                Log::warning("CAPS {$context} API request failed", [
                    'status' => $response->status(),
                    'request_url' => $requestUrl,
                    'body' => Str::limit($response->body(), 500),
                ]);

                return [
                    'ok' => false,
                    'message' => "Failed to fetch {$context} from CAPS (HTTP {$response->status()}).",
                    'data' => [],
                ];
            }

            return [
                'ok' => true,
                'data' => $response->json(),
                'total' => data_get($response->json(), 'totalElements', 0),
            ];
        } catch (\Throwable $e) {
            Log::error("CAPS {$context} API exception", [
                'error' => $e->getMessage(),
                'request_url' => $requestUrl,
            ]);

            return [
                'ok' => false,
                'message' => "Unable to reach CAPS API for {$context}.",
                'data' => [],
            ];
        }
    }

    private function buildRequestUrl(string $baseUrl, string $endpoint): string
    {
        $baseUrl = rtrim($baseUrl, '/');
        $endpoint = '/' . ltrim($endpoint, '/');

        if (Str::endsWith($baseUrl, $endpoint)) {
            return $baseUrl;
        }

        $basePath = (string) parse_url($baseUrl, PHP_URL_PATH);
        if ($basePath !== '' && $basePath !== '/' && Str::startsWith($endpoint, $basePath . '/')) {
            $endpoint = '/' . ltrim(Str::after($endpoint, $basePath), '/');
        }

        return $baseUrl . $endpoint;
    }

    private function resolveAccessToken(string $authUrl, string $username, string $password, bool $verifySsl): ?string
    {
        $sessionJwt = rescue(fn () => session('caps_jwt'), null, false);
        if (is_string($sessionJwt) && $sessionJwt !== '') {
            return $sessionJwt;
        }

        $cacheTtl = (int) config('services.casey.token_cache_ttl', 50);
        $cacheKey = 'casey_api_token_' . md5($authUrl . '|' . $username);

        if ($cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        try {
            $client = Http::timeout(15)
                ->connectTimeout(8)
                ->retry(1, 300, null, false)
                ->acceptJson();

            if (!$verifySsl) {
                $client = $client->withoutVerifying();
            }

            $response = $client->post($authUrl, [
                'username' => $username,
                'password' => $password,
            ]);

            if ($response->failed()) {
                return null;
            }

            $token = data_get($response->json(), 'token')
                ?? data_get($response->json(), 'accessToken')
                ?? data_get($response->json(), 'access_token')
                ?? data_get($response->json(), 'jwt')
                ?? data_get($response->json(), 'data.token')
                ?? data_get($response->json(), 'data.accessToken');

            if (!is_string($token) || trim($token) === '') {
                return null;
            }

            if ($cacheTtl > 0) {
                Cache::put($cacheKey, $token, now()->addMinutes($cacheTtl));
            }

            return $token;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
