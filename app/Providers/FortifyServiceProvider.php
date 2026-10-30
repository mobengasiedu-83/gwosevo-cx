<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => Inertia::render('Auth/Login'));
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', strtolower($request->email))->first();
            if ($user && $user->active && $user->tenant_id && Hash::check($request->password, $user->password)) {
                return $user;
            }
            // Keep unknown-account password work comparable to a known account.
            if (! $user) {
                Hash::check($request->password, '$2y$12$u1qYHfYJFpgjRNWT5WlOmeUQ98QPqAM4PlwQoJkzIFjGZErXkvOvW');
            }

            return null;
        });
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(5)->by(strtolower((string) $r->email).'|'.$r->ip()), Limit::perMinute(30)->by($r->ip())]);
        RateLimiter::for('two-factor', fn (Request $r) => Limit::perMinute(5)->by($r->session()->get('login.id').'|'.$r->ip()));
    }
}
