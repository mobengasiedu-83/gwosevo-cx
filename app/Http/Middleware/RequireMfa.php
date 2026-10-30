<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireMfa
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('fortify.mfa_enabled')) {
            return $next($request);
        }

        $user = $request->user();
        $privileged = $user && ($user->can('contact-center.manage') || $user->can('users.manage') || $user->can('roles.manage') || $user->can('settings.update') || $user->getAllPermissions()->contains(fn ($p) => preg_match('/\.(create|update|delete)$/', $p->name)));
        if ($privileged && ! $user->two_factor_confirmed_at) {
            return $request->expectsJson() ? response()->json(['message' => 'Set up two-factor authentication before administering the contact center.', 'redirect' => route('security')], 403) : redirect()->route('security');
        }

        return $next($request);
    }
}
