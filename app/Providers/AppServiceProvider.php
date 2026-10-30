<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('testing')) {
            Vite::useHotFile(storage_path('testing/nonexistent.hot'));
        }
        Event::listen(Login::class, function ($event) {
            if (request()->hasSession()) {
                request()->session()->put('auth_version', $event->user->auth_version);
            }
            Audit::record($event->user, 'login', 'authentication', (string) $event->user->id);
        });
        Event::listen(Logout::class, function ($event) {
            if ($event->user) {
                Audit::record($event->user, 'logout', 'authentication', (string) $event->user->id);
            }
        });
        Event::listen(Failed::class, function ($event) {
            $user = $event->user ?? User::where('email', strtolower($event->credentials['email'] ?? ''))->first();
            if ($user) {
                Audit::record($user, 'login_failed', 'authentication', (string) $user->id);
            }
        });
        foreach ([TwoFactorAuthenticationEnabled::class, TwoFactorAuthenticationConfirmed::class, TwoFactorAuthenticationDisabled::class] as $eventClass) {
            Event::listen($eventClass, function ($event) {
                $event->user->increment('auth_version');
                if (request()->hasSession()) {
                    request()->session()->put('auth_version', $event->user->auth_version);
                }
                Audit::record($event->user, class_basename($event), 'authentication', (string) $event->user->id);
            });
        }
        Horizon::auth(fn ($request) => $request->user()?->hasRole('Super Admin') && (! config('fortify.mfa_enabled') || $request->user()?->two_factor_confirmed_at));
    }
}
