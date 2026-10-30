<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureActiveSession
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            if (! $user->active || $request->session()->get('auth_version', $user->auth_version) !== $user->auth_version) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson() ? response()->json(['message' => 'Session revoked. Sign in again.'], 401) : redirect()->route('login');
            }
            $request->session()->put('auth_version', $user->auth_version);
        }

        return $next($request);
    }
}
