<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pulls the canonical Company / Municipality master data from CAPS and upserts
 * it into the Submission Tracker. CAPS is treated as the system of record:
 * conflicts are resolved by overwriting the local copy with whatever CAPS
 * returns.
 *
 * Each Tracker row is keyed back to CAPS by the `casey_id` column. Locally-
 * created rows that have no `casey_id` are left untouched – the operator can
 * decide whether to manually link them by populating `casey_id` later.
 */
class CaseyReferenceDataService
{
    /**
     * Sync municipalities and companies (core reference data).
     *
     * @return array{ok:bool,municipalities:array,companies:array,message:?string}
     */
    public function syncAll(): array
    {
        $municipalities = $this->syncMunicipalities();
        $companies = $this->syncCompanies();

        return [
            'ok' => $municipalities['ok'] && $companies['ok'],
            'municipalities' => $municipalities,
            'companies' => $companies,
            'message' => null,
        ];
    }

    /**
     * Full sync including members and policies (heavier, typically run daily).
     */
    public function syncAllWithMembersAndPolicies(): array
    {
        $base = $this->syncAll();
        $members = $this->syncMembers();
        $policies = $this->syncPolicies();

        return array_merge($base, [
            'ok' => $base['ok'] && $members['ok'] && $policies['ok'],
            'members' => $members,
            'policies' => $policies,
        ]);
    }

    /**
     * @return array{ok:bool,fetched:int,created:int,updated:int,skipped:int,message:?string}
     */
    public function syncMunicipalities(): array
    {
        $payload = $this->fetchFromCaps('municipalities_endpoint');

        if (! $payload['ok']) {
            return $this->emptyResult($payload['message']);
        }

        $rows = $this->normalizeRows($payload['data']);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$created, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $caseyId = $this->extractCaseyId($row);
                if ($caseyId === null) {
                    $skipped++;
                    continue;
                }

                $attributes = [
                    'name' => (string) ($row['orgName'] ?? $row['name'] ?? $caseyId),
                    'province' => $this->extractProvince($row),
                    'code' => $this->extractCode($row),
                    'casey_synced_at' => Carbon::now(),
                ];

                $municipality = Municipality::where('casey_id', $caseyId)->first();

                if ($municipality === null) {
                    Municipality::create(array_merge(['casey_id' => $caseyId], $attributes));
                    $created++;
                } else {
                    $municipality->fill($attributes);
                    if ($municipality->isDirty()) {
                        $municipality->save();
                        $updated++;
                    } else {
                        // Always bump the synced_at timestamp so we can tell how
                        // recently a row was last reconciled with CAPS, even when
                        // nothing else changed.
                        $municipality->forceFill(['casey_synced_at' => Carbon::now()])->save();
                    }
                }
            }
        });

        Log::info('Casey municipalities sync complete', [
            'fetched' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return [
            'ok' => true,
            'fetched' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'message' => null,
        ];
    }

    /**
     * @return array{ok:bool,fetched:int,created:int,updated:int,skipped:int,message:?string}
     */
    public function syncCompanies(): array
    {
        $payload = $this->fetchFromCaps('companies_endpoint');

        if (! $payload['ok']) {
            return $this->emptyResult($payload['message']);
        }

        $rows = $this->normalizeRows($payload['data']);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        // Pre-load the casey_id -> local id mapping for municipalities so we
        // can resolve the CAPS parent organisation reference into the local
        // foreign key without N+1 queries.
        $municipalityMap = Municipality::query()
            ->whereNotNull('casey_id')
            ->pluck('id', 'casey_id');

        // Build an areaCaseyId -> municipalityCaseyId lookup from the CAPS
        // municipalities response. CAPS does not put a municipality link on
        // a company directly - instead each company has a list of
        // `deductionCodes`, each of which carries the area it operates in,
        // and each municipality is keyed to a single `areaId`. So the chain
        // is: company.deductionCodes[i].areaId -> municipality.areaId ->
        // municipality.id (which we already store as casey_id locally).
        $areaToMunicipality = $this->buildAreaMunicipalityMap();

        DB::transaction(function () use ($rows, $municipalityMap, $areaToMunicipality, &$created, &$updated, &$skipped) {
            foreach ($rows as $row) {
                $caseyId = $this->extractCaseyId($row);
                if ($caseyId === null) {
                    $skipped++;
                    continue;
                }

                $municipalityCaseyId = $this->extractMunicipalityCaseyId($row, $areaToMunicipality);
                $municipalityLocalId = $municipalityCaseyId !== null
                    ? $municipalityMap->get($municipalityCaseyId)
                    : null;

                // `companies.municipality_id` is nullable. Companies that
                // CAPS has not yet scoped to an area (empty deductionCodes)
                // are imported with municipality_id=null and will acquire
                // the link automatically on a future sync once CAPS assigns
                // them deduction codes.
                if ($municipalityLocalId === null) {
                    Log::info('Casey company imported without municipality scope', [
                        'casey_id' => $caseyId,
                        'name' => $row['orgName'] ?? $row['name'] ?? null,
                    ]);
                }

                $attributes = [
                    'name' => (string) ($row['orgName'] ?? $row['name'] ?? $caseyId),
                    'registration_number' => $row['registrationNumber'] ?? $row['registration_number'] ?? null,
                    'contact_email' => $row['email'] ?? $row['contactEmail'] ?? null,
                    'status' => $this->extractStatus($row),
                    'municipality_id' => $municipalityLocalId,
                    'casey_synced_at' => Carbon::now(),
                ];

                $company = Company::where('casey_id', $caseyId)->first();

                if ($company === null) {
                    Company::create(array_merge(['casey_id' => $caseyId], $attributes));
                    $created++;
                } else {
                    $company->fill($attributes);
                    if ($company->isDirty()) {
                        $company->save();
                        $updated++;
                    } else {
                        $company->forceFill(['casey_synced_at' => Carbon::now()])->save();
                    }
                }
            }
        });

        Log::info('Casey companies sync complete', [
            'fetched' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        return [
            'ok' => true,
            'fetched' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'message' => null,
        ];
    }

    /**
     * Issues an authenticated GET against CAPS for either the municipalities
     * or companies endpoint. Reuses the same auth strategy as
     * CaseyPremiumBatchService so both services share one cached bearer token.
     *
     * @return array{ok:bool,data:mixed,message:?string}
     */
    private function fetchFromCaps(string $endpointKey): array
    {
        $baseUrl = trim((string) config('services.casey.base_url', ''));
        $endpoint = trim((string) config("services.casey.$endpointKey", ''));
        $username = (string) config('services.casey.username', '');
        $password = (string) config('services.casey.password', '');
        $verifySsl = (bool) config('services.casey.verify_ssl', true);
        $onlyActive = (bool) config('services.casey.sync_only_active', true);

        if ($baseUrl === '' || $endpoint === '' || $username === '' || $password === '') {
            return [
                'ok' => false,
                'data' => [],
                'message' => 'Casey API base URL, credentials or endpoint are missing.',
            ];
        }

        $requestUrl = $this->buildRequestUrl($baseUrl, $endpoint);

        try {
            $client = $this->buildHttpClient($verifySsl);
            $token = $this->resolveAccessToken($baseUrl, $username, $password, $verifySsl);

            if ($token) {
                $client = $client->withToken($token);
            } else {
                $client = $client->withBasicAuth($username, $password);
            }

            $response = $client->get($requestUrl, [
                'active' => $onlyActive ? 'true' : 'false',
            ]);

            if ($response->failed()) {
                Log::warning('Casey reference data fetch failed', [
                    'endpoint_key' => $endpointKey,
                    'request_url' => $requestUrl,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 1200),
                ]);

                return [
                    'ok' => false,
                    'data' => [],
                    'message' => "CAPS returned HTTP {$response->status()} for $endpointKey.",
                ];
            }

            return [
                'ok' => true,
                'data' => $response->json(),
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Casey reference data fetch exception', [
                'endpoint_key' => $endpointKey,
                'request_url' => $requestUrl,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'data' => [],
                'message' => 'Unable to reach CAPS. Verify CASEY_API_BASE_URL and connectivity.',
            ];
        }
    }

    private function buildHttpClient(bool $verifySsl): PendingRequest
    {
        $client = Http::timeout(30)
            ->connectTimeout(8)
            ->retry(2, 500, null, false)
            ->acceptJson();

        if (! $verifySsl) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    private function resolveAccessToken(string $baseUrl, string $username, string $password, bool $verifySsl): ?string
    {
        // Prefer the logged-in user's own CAPS JWT (stored during SSO login)
        // so API calls act as that user, not the shared service account.
        // Falls through to config credentials for CLI / scheduler contexts.
        $sessionJwt = rescue(fn () => session('caps_jwt'), null, false);
        if (is_string($sessionJwt) && $sessionJwt !== '') {
            return $sessionJwt;
        }

        $authEndpoint = trim((string) config('services.casey.auth_endpoint', '/casey/auth/sign-in'));
        $authUrl = $this->buildRequestUrl($baseUrl, $authEndpoint);

        $cacheTtl = (int) config('services.casey.token_cache_ttl', 50);
        $cacheKey = 'casey_api_token_' . md5($authUrl . '|' . $username);

        if ($cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        try {
            $response = $this->buildHttpClient($verifySsl)
                ->post($authUrl, [
                    'username' => $username,
                    'password' => $password,
                ]);

            if ($response->failed()) {
                Log::warning('Casey auth failed during reference data sync', [
                    'auth_url' => $authUrl,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $token = data_get($response->json(), 'token')
                ?? data_get($response->json(), 'accessToken')
                ?? data_get($response->json(), 'access_token')
                ?? data_get($response->json(), 'jwt')
                ?? data_get($response->json(), 'data.token')
                ?? data_get($response->json(), 'data.accessToken');

            if (! is_string($token) || trim($token) === '') {
                return null;
            }

            if ($cacheTtl > 0) {
                Cache::put($cacheKey, $token, now()->addMinutes($cacheTtl));
            }

            return $token;
        } catch (\Throwable $e) {
            Log::warning('Casey auth exception during reference data sync', [
                'auth_url' => $authUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
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

    /**
     * CAPS endpoints sometimes wrap the list in a `data` / `content` envelope
     * (Spring Pageable) and sometimes return a bare array. Normalise to a
     * plain list of associative arrays.
     */
    private function normalizeRows(mixed $payload): array
    {
        if (is_array($payload) && array_is_list($payload)) {
            return $payload;
        }

        if (is_array($payload)) {
            foreach (['data', 'content', 'items', 'results'] as $key) {
                if (isset($payload[$key]) && is_array($payload[$key])) {
                    return array_values($payload[$key]);
                }
            }
        }

        return [];
    }

    private function extractCaseyId(array $row): ?string
    {
        $candidates = [
            $row['id'] ?? null,
            $row['orgId'] ?? null,
            $row['organizationId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = $this->stringifyScalar($candidate);
            if ($value !== null) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Resolve the municipality's CAPS id for a company row. We try direct
     * references first (for forward compatibility if CAPS ever adds one),
     * then fall back to the area-map lookup: grab the first `areaId` from
     * the company's deductionCodes (or its own `areaId` field) and translate
     * it via $areaToMunicipality.
     *
     * @param array<string,string>  $areaToMunicipality  areaCaseyId => municipalityCaseyId
     */
    private function extractMunicipalityCaseyId(array $row, array $areaToMunicipality = []): ?string
    {
        $direct = [
            $row['municipalityId'] ?? null,
            $row['parentOrgId'] ?? null,
            $row['parentId'] ?? null,
            data_get($row, 'parentOrganization.id'),
            data_get($row, 'municipality.id'),
        ];
        foreach ($direct as $candidate) {
            $value = $this->stringifyScalar($candidate);
            if ($value !== null) {
                return $value;
            }
        }

        if ($areaToMunicipality === []) {
            return null;
        }

        // Indirect: via the first area referenced on this company.
        $areaCandidates = [];
        $deductionCodes = $row['deductionCodes'] ?? null;
        if (is_array($deductionCodes)) {
            foreach ($deductionCodes as $code) {
                if (! is_array($code)) {
                    continue;
                }
                $areaCandidates[] = $code['areaId'] ?? null;
                $areaCandidates[] = data_get($code, 'area.id');
            }
        }
        $areaCandidates[] = $row['areaId'] ?? null;
        $areaCandidates[] = data_get($row, 'area.id');

        foreach ($areaCandidates as $raw) {
            $areaCaseyId = $this->stringifyScalar($raw);
            if ($areaCaseyId !== null && isset($areaToMunicipality[$areaCaseyId])) {
                return $areaToMunicipality[$areaCaseyId];
            }
        }

        return null;
    }

    /**
     * Returns a map of areaCaseyId -> municipalityCaseyId by re-fetching the
     * CAPS municipalities endpoint. Cheap because that list is small (dozens
     * of rows, not thousands). Empty on any failure - callers should treat
     * an empty map as "no indirect resolution available" and skip the row
     * rather than crash the whole sync.
     *
     * @return array<string,string>
     */
    private function buildAreaMunicipalityMap(): array
    {
        $payload = $this->fetchFromCaps('municipalities_endpoint');
        if (! $payload['ok']) {
            return [];
        }

        $map = [];
        foreach ($this->normalizeRows($payload['data']) as $row) {
            $municipalityCaseyId = $this->extractCaseyId($row);
            if ($municipalityCaseyId === null) {
                continue;
            }
            $areaIds = array_filter([
                $row['areaId'] ?? null,
                data_get($row, 'area.id'),
            ], fn ($v) => $v !== null);
            foreach ($areaIds as $areaId) {
                $areaCaseyId = $this->stringifyScalar($areaId);
                if ($areaCaseyId !== null) {
                    $map[$areaCaseyId] = $municipalityCaseyId;
                }
            }
        }
        return $map;
    }

    private function extractProvince(array $row): ?string
    {
        // CAPS returns the province as either a plain string or a nested
        // object. The nested object's own name-field is `province.province`
        // (e.g. {"id": "...", "province": "Gauteng"}), hence the explicit
        // check — `province.name` is kept as a fallback for other shapes.
        $candidates = [
            $row['province'] ?? null,
            data_get($row, 'province.province'),
            data_get($row, 'province.name'),
            data_get($row, 'province.provinceName'),
            $row['provinceName'] ?? null,
            data_get($row, 'area.province.province'),
            data_get($row, 'area.province.name'),
            data_get($row, 'area.provinceName'),
        ];

        foreach ($candidates as $candidate) {
            $value = $this->stringifyScalar($candidate);
            if ($value !== null) {
                return $value;
            }
        }

        return config('services.casey.sync_default_province');
    }

    private function extractCode(array $row): ?string
    {
        $candidates = [
            $row['code'] ?? null,
            $row['orgCode'] ?? null,
            data_get($row, 'area.areaCode'),
            data_get($row, 'area.code'),
        ];

        foreach ($candidates as $candidate) {
            $value = $this->stringifyScalar($candidate);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Return the given value as a trimmed non-empty string, or null if the
     * value is not scalar, is null, or stringifies to empty. Prevents the
     * "Array to string conversion" crash that used to happen when CAPS
     * returned a nested object where we expected a string.
     */
    private function stringifyScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return null;
    }

    private function extractStatus(array $row): string
    {
        // Collect the first non-null candidate from the nested shapes CAPS
        // uses, then coerce it through stringifyScalar so an unexpected
        // array/object falls back to "active" instead of crashing.
        $raw = null;
        foreach ([
            $row['status'] ?? null,
            $row['statusId'] ?? null,
            data_get($row, 'statusType.name'),
            data_get($row, 'status.name'),
        ] as $candidate) {
            if ($candidate !== null) {
                $raw = $candidate;
                break;
            }
        }

        $isActive = match (true) {
            $raw === null => true,
            is_bool($raw) => $raw,
            is_int($raw) => $raw === 1,
            is_array($raw) => true, // unrecognised object - fail open
            default => ! in_array(strtoupper((string) $raw), ['INACTIVE', 'DEACTIVATED', 'DISABLED', '0', 'FALSE'], true),
        };

        return $isActive ? 'active' : 'inactive';
    }

    // ── Member sync ───────────────────────────────────────────────────────

    /**
     * Sync members from CAPS.
     *
     * @return array{ok:bool,fetched:int,created:int,updated:int,skipped:int,message:?string}
     */
    public function syncMembers(): array
    {
        $baseUrl = trim((string) config('services.casey.base_url', ''));
        $verifySsl = (bool) config('services.casey.verify_ssl', true);

        if ($baseUrl === '') {
            return $this->emptyResult('Casey API base URL is missing.');
        }

        $endpoint = '/v1/member/api/members';
        $requestUrl = $this->buildRequestUrl($baseUrl, $endpoint);

        try {
            $client = $this->buildHttpClient($verifySsl);
            $token = $this->resolveAccessToken(
                $baseUrl,
                (string) config('services.casey.username', ''),
                (string) config('services.casey.password', ''),
                $verifySsl
            );

            if ($token) {
                $client = $client->withToken($token);
            }

            // Fetch members with pagination
            $page = 0;
            $size = 500;
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $totalFetched = 0;

            do {
                $response = $client->get($requestUrl, [
                    'page' => $page,
                    'size' => $size,
                ]);

                if ($response->failed()) {
                    Log::warning('Casey members fetch failed', [
                        'status' => $response->status(),
                        'page' => $page,
                    ]);
                    break;
                }

                $data = $response->json();
                $rows = $data['content'] ?? $data['data'] ?? $data;
                if (!is_array($rows)) break;

                $totalFetched += count($rows);

                DB::transaction(function () use ($rows, &$created, &$updated, &$skipped) {
                    foreach ($rows as $row) {
                        $caseyId = (string) ($row['id'] ?? '');
                        if ($caseyId === '') {
                            $skipped++;
                            continue;
                        }

                        // memberStatus and area may be nested objects from CAPS
                        $memberStatus = $row['memberStatus'] ?? null;
                        $statusString = is_array($memberStatus)
                            ? ($memberStatus['status'] ?? null)
                            : ($memberStatus ?? $row['status'] ?? null);

                        $areaCode = $row['areaId'] ?? $row['area_id'] ?? null;
                        if (is_array($areaCode)) {
                            $areaCode = $areaCode['areaCode'] ?? $areaCode['id'] ?? null;
                        }
                        // If areaId is the CAPS ID string but area.areaCode has the human code, prefer that
                        $areaObj = $row['area'] ?? null;
                        if (is_array($areaObj) && isset($areaObj['areaCode'])) {
                            $areaCode = $areaObj['areaCode'];
                        }

                        $attributes = [
                            'id_number' => $row['idNumber'] ?? $row['id_number'] ?? null,
                            'pay_number' => $row['payNumber'] ?? $row['pay_number'] ?? null,
                            'first_name' => $row['firstName'] ?? $row['first_name'] ?? null,
                            'surname' => $row['surName'] ?? $row['surname'] ?? $row['sur_name'] ?? null,
                            'municipality_casey_id' => $row['organizationId'] ?? $row['organization_id'] ?? null,
                            'area_code' => is_string($areaCode) ? $areaCode : null,
                            'status' => is_string($statusString) ? $statusString : null,
                            'cell_number' => $row['cellNumber'] ?? $row['cell_number'] ?? null,
                            'email' => $row['email'] ?? null,
                            'employment_start_date' => $row['empStartDate'] ?? $row['emp_start_date'] ?? null,
                            'employment_end_date' => $row['empEndDate'] ?? $row['emp_end_date'] ?? null,
                            'casey_synced_at' => Carbon::now(),
                        ];

                        $member = \App\Models\CapsMember::where('casey_id', $caseyId)->first();
                        if ($member === null) {
                            \App\Models\CapsMember::create(array_merge(['casey_id' => $caseyId], $attributes));
                            $created++;
                        } else {
                            $member->fill($attributes);
                            if ($member->isDirty()) {
                                $member->save();
                                $updated++;
                            } else {
                                $member->forceFill(['casey_synced_at' => Carbon::now()])->save();
                            }
                        }
                    }
                });

                $totalPages = $data['totalPages'] ?? ceil(($data['totalElements'] ?? 0) / $size);
                $page++;
            } while ($page < $totalPages && count($rows) === $size);

            Log::info('Casey members sync complete', compact('totalFetched', 'created', 'updated', 'skipped'));

            return [
                'ok' => true,
                'fetched' => $totalFetched,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Casey members sync exception', ['error' => $e->getMessage()]);
            return $this->emptyResult('Members sync failed: ' . $e->getMessage());
        }
    }

    // ── Policy sync ───────────────────────────────────────────────────────

    /**
     * Sync policies from CAPS.
     *
     * @return array{ok:bool,fetched:int,created:int,updated:int,skipped:int,message:?string}
     */
    public function syncPolicies(): array
    {
        $baseUrl = trim((string) config('services.casey.base_url', ''));
        $verifySsl = (bool) config('services.casey.verify_ssl', true);

        if ($baseUrl === '') {
            return $this->emptyResult('Casey API base URL is missing.');
        }

        $endpoint = '/v1/premiums/status/fetch';
        $requestUrl = $this->buildRequestUrl($baseUrl, $endpoint);

        try {
            $client = $this->buildHttpClient($verifySsl);
            $token = $this->resolveAccessToken(
                $baseUrl,
                (string) config('services.casey.username', ''),
                (string) config('services.casey.password', ''),
                $verifySsl
            );

            if ($token) {
                $client = $client->withToken($token);
            }

            // Use longer timeout for policies (large dataset)
            $client = $client->timeout(120);

            $page = 0;
            $size = 500;
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $totalFetched = 0;

            do {
                $response = $client->get($requestUrl, [
                    'page' => $page,
                    'size' => $size,
                ]);

                if ($response->failed()) {
                    Log::warning('Casey policies fetch failed', [
                        'status' => $response->status(),
                        'page' => $page,
                    ]);
                    break;
                }

                $data = $response->json();
                // CAPS wraps policies in policyStatuses.content
                $envelope = $data['policyStatuses'] ?? $data;
                $rows = $envelope['content'] ?? $data['content'] ?? $data['data'] ?? [];
                if (!is_array($rows) || empty($rows)) break;

                $totalFetched += count($rows);

                DB::transaction(function () use ($rows, &$created, &$updated, &$skipped) {
                    foreach ($rows as $row) {
                        $caseyId = (string) ($row['id'] ?? '');
                        if ($caseyId === '') {
                            $skipped++;
                            continue;
                        }

                        // statusName is a string; policyStatus may be an object
                        $policyStatus = $row['statusName'] ?? $row['policyStatus'] ?? $row['policy_status'] ?? null;
                        if (is_array($policyStatus)) {
                            $policyStatus = $policyStatus['status'] ?? $policyStatus['name'] ?? null;
                        }

                        $attributes = [
                            'policy_code' => $row['policyCode'] ?? $row['policy_code'] ?? null,
                            'member_casey_id' => $row['memberId'] ?? $row['member_id'] ?? null,
                            'company_casey_id' => $row['organizationId'] ?? $row['organization_id'] ?? null,
                            'company_name' => $row['organizationName'] ?? $row['organization_name'] ?? null,
                            'premium_amount' => $row['premiumAmount'] ?? $row['premium_amount'] ?? null,
                            'balance_amount' => $row['balanceAmount'] ?? $row['balance_amount'] ?? null,
                            'deduction_code' => $row['deductionCode'] ?? $row['deduction_code'] ?? null,
                            'policy_status' => is_string($policyStatus) ? $policyStatus : null,
                            'term' => $row['term'] ?? null,
                            'casey_synced_at' => Carbon::now(),
                        ];

                        $policy = \App\Models\CapsPolicy::where('casey_id', $caseyId)->first();
                        if ($policy === null) {
                            \App\Models\CapsPolicy::create(array_merge(['casey_id' => $caseyId], $attributes));
                            $created++;
                        } else {
                            $policy->fill($attributes);
                            if ($policy->isDirty()) {
                                $policy->save();
                                $updated++;
                            } else {
                                $policy->forceFill(['casey_synced_at' => Carbon::now()])->save();
                            }
                        }
                    }
                });

                $totalPages = $envelope['totalPages'] ?? ceil(($envelope['totalElements'] ?? 0) / $size);
                $page++;
            } while ($page < $totalPages && count($rows) === $size);

            Log::info('Casey policies sync complete', compact('totalFetched', 'created', 'updated', 'skipped'));

            return [
                'ok' => true,
                'fetched' => $totalFetched,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'message' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('Casey policies sync exception', ['error' => $e->getMessage()]);
            return $this->emptyResult('Policies sync failed: ' . $e->getMessage());
        }
    }

    private function emptyResult(?string $message): array
    {
        return [
            'ok' => false,
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'message' => $message,
        ];
    }
}
