<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CaseyPremiumBatchService
{
    public function fetchDetailedInfo(int $policyBatchId): array
    {
        $baseUrl = trim((string) config('services.casey.base_url', ''));
        $authEndpoint = trim((string) config('services.casey.auth_endpoint', '/casey/auth/sign-in'));
        $endpoint = trim((string) config('services.casey.premium_batch_endpoint', '/casey/v1/premiums/batch/detailed_info'));
        $username = (string) config('services.casey.username', '');
        $password = (string) config('services.casey.password', '');
        $verifySsl = (bool) config('services.casey.verify_ssl', true);

        if ($baseUrl === '' || $username === '' || $password === '') {
            return [
                'ok' => false,
                'message' => 'Casey API credentials or base URL are missing.',
                'data' => [],
                'requestedPolicyBatchId' => $policyBatchId,
            ];
        }

        $requestUrl = $this->buildRequestUrl($baseUrl, $endpoint);
        $authUrl = $this->buildRequestUrl($baseUrl, $authEndpoint);

        try {
            $client = Http::timeout(20)
                ->connectTimeout(8)
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

            $response = $client->get($requestUrl, [
                'policyBatchId' => $policyBatchId,
                'criteria' => 'newPolicies',
                'page' => 0,
                'size' => 25,
                'sortColumn' => 'id',
                'sortDirection' => 'desc',
            ]);

            if ($response->failed()) {
                $status = $response->status();
                $message = match ($status) {
                    401, 403 => 'Casey API authentication failed. Check CASEY_API_USERNAME/CASEY_API_PASSWORD.',
                    404 => 'Casey premium batch endpoint was not found. Check CASEY_API_PREMIUM_BATCH_ENDPOINT.',
                    default => 'Failed to fetch premium batch details.',
                };

                Log::warning('Casey premium batch API request failed', [
                    'status' => $status,
                    'policy_batch_id' => $policyBatchId,
                    'request_url' => $requestUrl,
                    'auth_mode' => $token ? 'bearer' : 'basic',
                    'body' => Str::limit($response->body(), 1200),
                ]);

                return [
                    'ok' => false,
                    'message' => $message,
                    'data' => [],
                    'requestedPolicyBatchId' => $policyBatchId,
                ];
            }

            $payload = $response->json();
            $responsePolicyBatchId = data_get($payload, 'policyBatch.id', $policyBatchId);

            return [
                'ok' => true,
                'message' => null,
                'data' => $payload,
                'requestedPolicyBatchId' => $policyBatchId,
                'responsePolicyBatchId' => $responsePolicyBatchId,
            ];
        } catch (\Throwable $e) {
            Log::error('Casey premium batch API exception', [
                'error' => $e->getMessage(),
                'policy_batch_id' => $policyBatchId,
                'request_url' => $requestUrl,
            ]);

            return [
                'ok' => false,
                'message' => 'Unable to reach premium batch API. Verify CASEY_API_BASE_URL and server connectivity.',
                'data' => [],
                'requestedPolicyBatchId' => $policyBatchId,
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
        // Prefer the logged-in user's own CAPS JWT (stored during SSO login)
        // so API calls act as that user, not the shared service account.
        // Falls through to config credentials for CLI / scheduler contexts.
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
                Log::warning('Casey auth request failed', [
                    'status' => $response->status(),
                    'auth_url' => $authUrl,
                ]);
                return null;
            }

            $token = data_get($response->json(), 'token')
                ?? data_get($response->json(), 'accessToken')
                ?? data_get($response->json(), 'access_token')
                ?? data_get($response->json(), 'jwt')
                ?? data_get($response->json(), 'data.token')
                ?? data_get($response->json(), 'data.accessToken');

            if (!is_string($token) || trim($token) === '') {
                Log::warning('Casey auth token missing in response', [
                    'auth_url' => $authUrl,
                    'response_keys' => array_keys((array) $response->json()),
                ]);
                return null;
            }

            if ($cacheTtl > 0) {
                Cache::put($cacheKey, $token, now()->addMinutes($cacheTtl));
            }

            return $token;
        } catch (\Throwable $e) {
            Log::warning('Casey auth exception', [
                'auth_url' => $authUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
