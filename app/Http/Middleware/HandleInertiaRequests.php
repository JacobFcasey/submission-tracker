<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $unreadNotifications = 0;

        if ($user) {
            $unreadNotifications = $user->unreadNotifications()->count();
        }

        return array_merge(parent::share($request), [
            'csrf_token' => csrf_token(),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_number' => $user->employee_number,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $user->getAllPermissions()->pluck('name')
                ] : null,
            ],

            'notifications' => [
                'unread_count' => $unreadNotifications,
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],

            'appName' => config('app.name'),

            'sso' => [
                'enabled' => (bool) config('services.casey.sso_enabled', false),
                'serviceUrl' => (string) config('services.casey.sso_service_url', ''),
                'apiSecret' => (string) config('services.casey.sso_api_secret', ''),
            ],
        ]);
    }
}
