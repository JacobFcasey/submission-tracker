<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     *
     * When CAPS SSO is enabled and an upstream handoff URL is configured, an
     * unauthenticated visitor is transparently bounced through the CAPS
     * bridge page so they get logged in without ever seeing this form. The
     * `?sso=skip` query and the `casey_sso_skip` cookie act as a loop guard
     * after a failed attempt, falling back to the local login form.
     */
    public function create(Request $request)
    {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        // Check the SSO microservice for an active session — if found,
        // auto-login via the SSO endpoint instead of showing the form.
        $sso = app(\App\Services\SsoSessionService::class);
        $sessions = $sso->listSessions();
        foreach ($sessions as $s) {
            $token = $s['token'] ?? '';
            if ($token && !str_starts_with($token, 'local-session-') && ($s['source'] ?? '') === 'caps') {
                return redirect()->to('/auth/casey-sso?token=' . urlencode($token));
            }
        }

        // No active SSO session — show the login form
        return Inertia::render('Auth/Login', [
            'caseySsoBlocked' => false,
            'caseySsoBlockedReason' => null,
            'caseySignedOut' => $request->query('signed_out') === '1',
        ]);
    }

    private function shouldAutoRedirectToCasey(Request $request): bool
    {
        if (! (bool) config('services.casey.sso_enabled', false)) {
            return false;
        }
        if (! (bool) config('services.casey.sso_auto_redirect', true)) {
            return false;
        }
        if (trim((string) config('services.casey.sso_handoff_url', '')) === '') {
            return false;
        }
        if ($this->isCaseySsoBlocked($request)) {
            return false;
        }
        return true;
    }

    /**
     * Returns true when we should stay on the local login form rather than
     * bounce the user through CAPS. Triggered by ?sso=skip in the URL or by
     * the short-lived casey_sso_skip cookie that destroy()/fail() set.
     */
    private function isCaseySsoBlocked(Request $request): bool
    {
        if ($request->query('sso') === 'skip') {
            return true;
        }
        if ($request->cookie('casey_sso_skip')) {
            return true;
        }
        return false;
    }

    private function buildHandoffUrl(): string
    {
        $handoff = rtrim((string) config('services.casey.sso_handoff_url'), '?&');
        $callback = route('auth.casey.sso');
        $separator = str_contains($handoff, '?') ? '&' : '?';
        return $handoff . $separator . 'return=' . urlencode($callback);
    }

    /**
     * Handle an authentication attempt.
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'employee_number' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        // Attempt primary local login first.
        $authenticated = Auth::attempt([
            'employee_number' => $credentials['employee_number'],
            'password' => $credentials['password'],
        ], $remember);

        // Fallback for Casey-synced users:
        // verify against external bcrypt hash seeded from Casey user API.
        if (! $authenticated) {
            $user = User::where('employee_number', $credentials['employee_number'])->first();
            if ($user && is_string($user->external_password_hash) && $user->external_password_hash !== '') {
                if (Hash::check($credentials['password'], $user->external_password_hash)) {
                    Auth::login($user, $remember);
                    $authenticated = true;
                }
            }
        }

        if (! $authenticated) {
            AuditLogger::authEvent('failed_login', $user ?? null, [
                'employee_number' => $credentials['employee_number'],
                'remember' => $remember,
            ]);

            throw ValidationException::withMessages([
                'employee_number' => __('The provided credentials are incorrect.'),
            ]);
        }

        $request->session()->regenerate();
        $request->user()->updateLastLogin();
        $request->session()->put('sso_last_employee', $request->user()->employee_number);

        // Clear the SSO skip cookie so auto-SSO resumes after this login
        Cookie::queue(Cookie::forget('casey_sso_skip'));

        // Register session in the SSO microservice so CAPS can detect Tracker login.
        // Use the CAPS JWT if available, otherwise a local session identifier.
        $ssoToken = $request->session()->get('caps_jwt', '')
            ?: ('local-session-' . $request->session()->getId());
        app(\App\Services\SsoSessionService::class)->registerSession(
            $request->user()->employee_number,
            $ssoToken,
            'tracker',
            $request->user()->name,
            $request->user()->email,
        );

        // Auto-sync CAPS reference data if no municipalities/companies exist yet
        try {
            $hasMunicipalities = \App\Models\Municipality::exists();
            $hasCompanies = \App\Models\Company::exists();
            if (!$hasMunicipalities || !$hasCompanies) {
                \Illuminate\Support\Facades\Log::info('[CAPS Sync] Auto-syncing reference data on login');
                app(\App\Services\CaseyReferenceDataService::class)->syncAll();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[CAPS Sync] Auto-sync failed: ' . $e->getMessage());
        }

        AuditLogger::authEvent('logged_in', $request->user(), [
            'employee_number' => $request->user()->employee_number,
            'remember' => $remember,
            'session_id' => $request->session()->getId(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out.
     *
     * After the session is destroyed we queue the `casey_sso_skip` cookie so
     * that the immediate redirect to /login does NOT bounce the user through
     * the CAPS SSO bridge (which would still hold a valid JWT in local
     * storage and sign them straight back in). We also route the browser
     * through the CAPS logout bridge when it is configured, so that the
     * CAPS JWT is actively cleared from local storage on the way out. The
     * bridge redirects back to /login?sso=skip&caps_cleared=1, which tells
     * the login page it is safe to render the local form normally.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Remove session from SSO microservice so CAPS knows we logged out
            app(\App\Services\SsoSessionService::class)
                ->removeSession($user->employee_number, 'tracker');

            AuditLogger::authEvent('logged_out', $user, [
                'employee_number' => $user->employee_number,
                'session_id' => $request->session()->getId(),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * SSO-triggered logout via GET — called by the client-side polling JS
     * when it detects the SSO session was removed. No CSRF token needed.
     */
    public function ssoLogout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            AuditLogger::authEvent('logged_out', $user, [
                'employee_number' => $user->employee_number,
                'method' => 'sso_poll_logout',
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * If a CAPS logout bridge URL is configured and SSO is enabled, return
     * a fully-qualified URL that points at the bridge with a `return` query
     * parameter carrying the Tracker's post-logout landing page. Otherwise
     * return null so the caller falls back to the local login route.
     */
    private function buildLogoutTarget(): ?string
    {
        if (! (bool) config('services.casey.sso_enabled', false)) {
            return null;
        }
        $logoutUrl = trim((string) config('services.casey.sso_logout_url', ''));
        if ($logoutUrl === '') {
            return null;
        }
        $return = route('login', ['sso' => 'skip']);
        $separator = str_contains($logoutUrl, '?') ? '&' : '?';
        return rtrim($logoutUrl, '?&') . $separator . 'return=' . urlencode($return);
    }
}
