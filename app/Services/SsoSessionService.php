<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the Casey SSO Session microservice.
 *
 * Registers, checks, and removes sessions so CAPS and the Tracker
 * stay in sync without iframes or popups.
 */
class SsoSessionService
{
    private const TIMEOUT = 3;
    private const CONNECT_TIMEOUT = 2;

    private string $baseUrl;
    private string $apiSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.casey.sso_service_url', 'http://localhost:4000'), '/');
        $this->apiSecret = (string) config('services.casey.sso_api_secret', 'casey-sso-dev-secret');
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->withHeaders(['X-SSO-Key' => $this->apiSecret]);
    }

    /**
     * Register a session after login.
     */
    public function registerSession(string $employeeNumber, string $token, string $source = 'tracker', ?string $userName = null, ?string $email = null): bool
    {
        try {
            $response = $this->http()
                ->post("{$this->baseUrl}/sessions", [
                    'employeeNumber' => $employeeNumber,
                    'token' => $token,
                    'source' => $source,
                    'userName' => $userName,
                    'email' => $email,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('SSO service register failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check for an active session.
     *
     * Returns:
     *   ['exists' => true, ...]  — session found
     *   ['exists' => false]      — session definitely does not exist (404)
     *   null                     — microservice unreachable (don't act on this)
     */
    public function checkSession(string $employeeNumber): ?array
    {
        try {
            $response = $this->http()
                ->get("{$this->baseUrl}/sessions/" . urlencode($employeeNumber));

            if ($response->successful() && $response->json('exists')) {
                return $response->json();
            }

            // 404 = session definitely gone; other errors = service issue
            if ($response->status() === 404) {
                return ['exists' => false];
            }

            return null; // service error — treat as unreachable
        } catch (\Throwable $e) {
            Log::debug('SSO service check failed: ' . $e->getMessage());
            return null; // unreachable — don't act
        }
    }

    /**
     * List all active sessions from the microservice.
     * Used when we don't have an employee number breadcrumb.
     */
    public function listSessions(): array
    {
        try {
            $response = $this->http()
                ->get("{$this->baseUrl}/sessions");

            if ($response->successful()) {
                return $response->json('sessions') ?? [];
            }
            return [];
        } catch (\Throwable $e) {
            Log::debug('SSO service list failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Remove a session on logout.
     */
    public function removeSession(string $employeeNumber, string $source = 'tracker'): bool
    {
        try {
            $response = $this->http()
                ->delete("{$this->baseUrl}/sessions/" . urlencode($employeeNumber) . "?" . http_build_query(['source' => $source]));

            return $response->successful();
        } catch (\Throwable $e) {
            Log::debug('SSO service remove failed: ' . $e->getMessage());
            return false;
        }
    }
}
