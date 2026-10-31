<?php

use App\Http\Controllers\ContactCenterController;
use App\Http\Controllers\FoundationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\DomainPermission;
use App\Models\Extension;
use App\Services\Navigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => auth()->check() ? redirect(Navigation::home(auth()->user())) : redirect('/login'));
Route::middleware('auth')->group(function () {
    Route::get('/security', fn () => Inertia::render('Security/Index'))->name('security');
    Route::middleware('mfa')->group(function () {
        Route::get('/admin', function (Request $request) {
            $navigation = Navigation::catalogue($request->user(), 'admin');
            $resources = collect($navigation)->flatMap(fn ($group) => $group['items'])->pluck('resource')->filter(fn ($resource) => $resource && ! str_starts_with($resource, '@'))->unique()->values();
            $selected = $request->query('module', $resources->contains('sites') ? 'sites' : $resources->first());
            abort_if($selected && ! $resources->contains($selected), 403);
            if (! $selected) {
                return Inertia::render('Portal/Index', ['portal' => 'admin', 'navigation' => $navigation]);
            }

            // Extensions use a dedicated tabbed editor instead of the generic
            // record form because an endpoint carries device, voicemail and
            // routing configuration.
            if ($selected === 'extensions') {
                return Inertia::render('Admin/Extensions', [
                    'navigation' => $navigation,
                    'selectedModule' => $selected,
                    'options' => Extension::options(),
                ]);
            }

            return Inertia::render('Admin/Index', ['navigation' => $navigation, 'selectedModule' => $selected]);
        })->name('admin');
        Route::get('/supervisor', fn (Request $request) => app(PortalController::class)->index($request, 'supervisor'));
        Route::get('/agent', fn (Request $request) => app(PortalController::class)->index($request, 'agent'));
        Route::get('/portal/{portal}/{group}/{item}', [PortalController::class, 'show'])->whereIn('portal', ['admin', 'supervisor', 'agent']);
        Route::get('/admin-api/search', SearchController::class)->middleware('can:contact-center.view');
        Route::get('/admin-api/users', [ContactCenterController::class, 'users'])->middleware('can:contact-center.view');
        Route::get('/admin-api/audits', [ContactCenterController::class, 'audits'])->middleware('can:audit-logs.view');
        Route::get('/admin-api/settings', [FoundationController::class, 'settings'])->middleware('can:settings.view');
        Route::put('/admin-api/settings', [FoundationController::class, 'saveSettings'])->middleware('can:settings.update');
        Route::get('/admin-api/accounts', [FoundationController::class, 'users'])->middleware('can:users.view');
        Route::post('/admin-api/accounts', [FoundationController::class, 'saveUser'])->middleware('can:users.manage');
        Route::put('/admin-api/accounts/{id}', [FoundationController::class, 'saveUser'])->middleware('can:users.manage');
        Route::post('/admin-api/accounts/{id}/revoke', [FoundationController::class, 'revoke'])->middleware('can:users.manage');
        Route::patch('/admin-api/accounts/{id}/status', [FoundationController::class, 'userStatus'])->middleware('can:users.manage');
        Route::get('/admin-api/roles', [FoundationController::class, 'roles'])->middleware('can:roles.view');
        Route::post('/admin-api/roles', [FoundationController::class, 'saveRole'])->middleware('can:roles.manage');
        Route::put('/admin-api/roles/{id}', [FoundationController::class, 'saveRole'])->middleware('can:roles.manage');
        Route::prefix('admin-api/{resource}')->where(['resource' => implode('|', array_keys(ContactCenterController::RESOURCES))])->middleware(DomainPermission::class)->group(function () {
            Route::get('/', [ContactCenterController::class, 'index']);
            Route::get('/{id}', [ContactCenterController::class, 'show']);
            Route::post('/', [ContactCenterController::class, 'store']);
            Route::put('/{id}', [ContactCenterController::class, 'update']);
            Route::patch('/{id}/status', [ContactCenterController::class, 'status']);
            Route::delete('/{id}', [ContactCenterController::class, 'destroy']);
        });
    });
});
