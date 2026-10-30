<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'baseUrl' => url('/'),
            'mfaAvailable' => (bool) config('fortify.mfa_enabled'),
            'auth' => fn () => $request->user() ? [
                'name' => $request->user()->name, 'email' => $request->user()->email,
                'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                'roles' => $request->user()->roles->pluck('name'),
                'mfaEnabled' => (bool) $request->user()->two_factor_confirmed_at,
            ] : null,
        ];
    }
}
