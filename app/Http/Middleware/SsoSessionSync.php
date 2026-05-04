<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SsoSessionService;
use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bidirectional SSO sync via the session microservice.
 *
 * Logged in    + session confirmed gone (404) → log out
 * NOT logged in + session found               → redirect to SSO login
 * Microservice down                           → do nothing (graceful degradation)
 */
class SsoSessionSync
{
    public function __construct(private readonly SsoSessionService $sso) {}

    public function handle(Request $request, Closure $next)
    {
        if ($this->shouldSkip($request->path())) {
            return $next($request);
        }

        if (!(bool) config('services.casey.sso_enabled', false)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user) {
            $this->detectRemoteLogout($request, $user);
        } else {
            $redirect = $this->detectRemoteLogin($request);
            if ($redirect) return $redirect;
        }

        return $next($request);
    }

    private function detectRemoteLogout(Request $request, User $user): void
    {
        $lastCheck = (int) $request->session()->get('sso_check_at', 0);
        if (time() - $lastCheck < 15) return;
        $request->session()->put('sso_check_at', time());

        $emp = $user->employee_number;
        if (!$emp) return;

        $result = $this->sso->checkSession($emp);

        // null = microservice unreachable → do nothing (graceful degradation)
        if ($result === null) return;

        // Session confirmed gone (404) → log out
        if (isset($result['exists']) && $result['exists'] === false) {
            Log::info("[SSO] Remote logout detected for {$emp}");

            AuditLogger::authEvent('logged_out', $user, [
                'employee_number' => $emp,
                'method' => 'sso_remote_logout',
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    private function detectRemoteLogin(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        $sessions = $this->sso->listSessions();
        if (empty($sessions)) return null;

        // Find a CAPS session with a real JWT
        $token = null;
        $employeeNumber = null;
        foreach ($sessions as $s) {
            $t = $s['token'] ?? '';
            if ($t && !str_starts_with($t, 'local-session-') && ($s['source'] ?? '') === 'caps') {
                $token = $t;
                $employeeNumber = $s['employeeNumber'] ?? null;
                break;
            }
        }
        if (!$token) return null;

        // Pre-check: if the employee exists locally but is inactive or has
        // no roles, skip auto-login. This prevents bouncing users who have
        // CAPS access but no Tracker access into a redirect loop.
        if ($employeeNumber) {
            $user = User::where('employee_number', $employeeNumber)->first();
            if ($user && !$user->is_active) {
                Log::info("[SSO] Skipping auto-login for inactive user {$employeeNumber}");
                return null;
            }
            if ($user && method_exists($user, 'roles') && $user->roles->isEmpty()) {
                Log::info("[SSO] Skipping auto-login for user {$employeeNumber} with no Tracker roles");
                return null;
            }
        }

        Log::info('[SSO] Auto-login: found CAPS session, redirecting to SSO endpoint');
        return redirect()->to('/auth/casey-sso?token=' . urlencode($token));
    }

    private function shouldSkip(string $path): bool
    {
        foreach (['auth/', 'login', 'logout', 'api/', 'health', '_debugbar'] as $prefix) {
            if (str_starts_with($path, $prefix)) return true;
        }
        return false;
    }
}
