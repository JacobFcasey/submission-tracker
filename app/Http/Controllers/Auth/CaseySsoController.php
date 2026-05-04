<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CaseyJwtService;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Implements single sign-on from CAPS into the Submission Tracker.
 *
 * The CAPS frontend hands the user off to this endpoint with their existing
 * CAPS-issued JWT. The token's signature is verified using the shared HS256
 * secret (`com.casey.supportal.jwt.token.secretkey` in CAPS, mirrored as
 * CASEY_JWT_SHARED_SECRET on this side). The `sub` claim is treated as the
 * employee number, which already serves as the canonical identifier in the
 * Tracker (see AuthenticatedSessionController and the users table).
 *
 * Two transports are supported so the CAPS UI can use whichever is easier:
 *
 *   GET  /auth/casey-sso?token=<jwt>          - simple link / redirect
 *   POST /auth/casey-sso  (token in body)     - form post / fetch()
 *
 * Auto-provisioning is on by default (CASEY_SSO_AUTO_PROVISION=true). When a
 * user has no Tracker account yet, one is created from the JWT claims and
 * given the configured default role (CASEY_SSO_DEFAULT_ROLE).
 */
class CaseySsoController extends Controller
{
    public function __construct(private readonly CaseyJwtService $jwt)
    {
    }

    public function login(Request $request): RedirectResponse
    {
        if (! (bool) config('services.casey.sso_enabled', false)) {
            abort(404);
        }

        $token = $this->extractToken($request);
        if ($token === null) {
            return $this->fail($request, 'Missing CAPS SSO token.');
        }

        try {
            $claims = $this->jwt->verify($token);
        } catch (RuntimeException $e) {
            Log::warning('Casey SSO rejected token', ['error' => $e->getMessage()]);
            return $this->fail($request, 'CAPS SSO token could not be verified.');
        }

        $employeeNumber = trim((string) ($claims['sub'] ?? ''));
        if ($employeeNumber === '') {
            return $this->fail($request, 'CAPS SSO token did not include an employee number.');
        }

        $user = User::where('employee_number', $employeeNumber)->first();

        if ($user === null) {
            if (! (bool) config('services.casey.sso_auto_provision', true)) {
                return $this->fail($request, 'Your CAPS account is not provisioned in the Submission Tracker.');
            }
            $user = $this->provisionUser($employeeNumber, $claims);
        } else {
            $this->refreshFromClaims($user, $claims);
        }

        if (! $user->is_active) {
            return $this->fail($request, 'Your Submission Tracker account is deactivated.');
        }

        // Block SSO if user exists in CAPS but has no Tracker roles/permissions.
        // This prevents CAPS-only users from accessing the Tracker via SSO.
        if (method_exists($user, 'roles') && $user->roles->isEmpty()
            && !$user->hasRole(['admin', 'super-admin', 'superadmin', 'user'])) {
            return $this->fail($request, 'You do not have access to the Submission Tracker. Contact your administrator.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->updateLastLogin();

        // Persist the CAPS JWT so all subsequent CAPS API calls in this
        // session authenticate as this user, not as the shared service
        // account from .env.
        $request->session()->put('caps_jwt', $token);
        $request->session()->put('sso_last_employee', $employeeNumber);

        // Register session in the SSO microservice so CAPS knows we're logged in
        app(\App\Services\SsoSessionService::class)->registerSession(
            $employeeNumber, $token, 'tracker', $user->name, $user->email
        );

        // Auto-sync CAPS reference data if no municipalities/companies exist yet.
        // This ensures the first SSO login populates the data from CAPS.
        $this->autoSyncReferenceDataIfEmpty();

        AuditLogger::authEvent('logged_in', $user, [
            'employee_number' => $employeeNumber,
            'method' => 'casey_sso',
            'session_id' => $request->session()->getId(),
        ]);

        // When called from a hidden iframe (CAPS login sync), return a
        // minimal HTML response instead of redirecting to the dashboard.
        if ($request->query('silent') === '1') {
            return redirect()->to('/auth/casey-sso-ok');
        }

        $redirectRoute = (string) config('services.casey.sso_redirect_route', 'dashboard');
        return redirect()->intended(route($redirectRoute));
    }

    private function extractToken(Request $request): ?string
    {
        // Accept the token in priority order: bearer header > body > query.
        $bearer = (string) $request->bearerToken();
        if ($bearer !== '') {
            return $bearer;
        }
        $body = (string) $request->input('token', '');
        if ($body !== '') {
            return $body;
        }
        $query = (string) $request->query('token', '');
        return $query !== '' ? $query : null;
    }

    /**
     * @param array<string,mixed> $claims
     */
    private function provisionUser(string $employeeNumber, array $claims): User
    {
        $email = (string) ($claims['email']
            ?? $claims['preferred_username']
            ?? ($employeeNumber . '@casey.local'));

        $name = (string) ($claims['name']
            ?? trim(((string) ($claims['given_name'] ?? '')) . ' ' . ((string) ($claims['family_name'] ?? '')))
            ?: $employeeNumber);

        $user = User::create([
            'employee_number' => $employeeNumber,
            'name' => $name !== '' ? $name : $employeeNumber,
            'email' => $email,
            // Generate a random local password so the row is valid; the user
            // can never log in with it directly because they never see it.
            'password' => Str::random(48),
            'is_active' => true,
        ]);

        $defaultRole = (string) config('services.casey.sso_default_role', 'user');
        if ($defaultRole !== '' && method_exists($user, 'assignRole')) {
            try {
                $user->assignRole($defaultRole);
            } catch (\Throwable $e) {
                Log::warning('Casey SSO could not assign default role', [
                    'employee_number' => $employeeNumber,
                    'role' => $defaultRole,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        AuditLogger::authEvent('provisioned_via_sso', $user, [
            'employee_number' => $employeeNumber,
            'role' => $defaultRole,
        ]);

        return $user;
    }

    /**
     * Keep the local user's display fields in sync with whatever CAPS knows
     * about them, but never overwrite a non-empty local value with an empty
     * claim.
     *
     * @param array<string,mixed> $claims
     */
    private function refreshFromClaims(User $user, array $claims): void
    {
        $updates = [];

        $email = (string) ($claims['email'] ?? '');
        if ($email !== '' && $email !== $user->email) {
            $updates['email'] = $email;
        }

        $name = (string) ($claims['name'] ?? '');
        if ($name !== '' && $name !== $user->name) {
            $updates['name'] = $name;
        }

        if ($updates !== []) {
            $user->fill($updates)->save();
        }
    }

    /**
     * Silent SSO logout — called from a hidden iframe when the user signs
     * out of CAPS. Destroys the Tracker session and returns a minimal HTML
     * page (no redirect since this runs in an invisible iframe).
     */
    public function silentLogout(Request $request): \Illuminate\Http\Response
    {
        if (! (bool) config('services.casey.sso_enabled', false)) {
            abort(404);
        }

        $user = $request->user();

        if ($user) {
            AuditLogger::authEvent('logged_out', $user, [
                'employee_number' => $user->employee_number ?? '',
                'method' => 'casey_sso_silent',
                'session_id' => $request->session()->getId(),
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Return a minimal page that auto-closes the popup window
        return response(
            '<html><body><script>window.close();</script>Signed out. You may close this window.</body></html>', 200)
            ->header('Content-Type', 'text/html')
            ->header('Cache-Control', 'no-store');
    }

    /**
     * If no CAPS-synced municipalities or companies exist, trigger a sync
     * so the first login populates reference data from CAPS automatically.
     */
    private function autoSyncReferenceDataIfEmpty(): void
    {
        try {
            $hasMunicipalities = \App\Models\Municipality::exists();
            $hasCompanies = \App\Models\Company::exists();

            if (!$hasMunicipalities || !$hasCompanies) {
                Log::info('[CAPS Sync] Auto-syncing reference data on first SSO login');
                app(\App\Services\CaseyReferenceDataService::class)->syncAll();
            }
        } catch (\Throwable $e) {
            Log::warning('[CAPS Sync] Auto-sync failed: ' . $e->getMessage());
        }
    }

    private function fail(Request $request, string $message): RedirectResponse
    {
        AuditLogger::authEvent('failed_sso', null, [
            'message' => $message,
            'ip' => $request->ip(),
        ]);

        // Set a short-lived cookie so AuthenticatedSessionController::create()
        // will skip the auto-redirect and show the local login form. Without
        // this, the user would loop straight back through CAPS and see this
        // same error again.
        $skipSeconds = max(5, (int) config('services.casey.sso_skip_seconds', 60));
        Cookie::queue('casey_sso_skip', '1', minutes: max(1, (int) ceil($skipSeconds / 60)));

        return redirect()
            ->route('login', ['sso' => 'skip'])
            ->withErrors(['employee_number' => $message]);
    }
}
