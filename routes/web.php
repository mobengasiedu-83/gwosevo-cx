<?php

use App\Http\Controllers\ContactCenterController;
use App\Http\Controllers\FoundationController;
use App\Http\Middleware\DomainPermission;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect('/admin'));
Route::middleware('auth')->group(function () {
    Route::get('/security', fn () => Inertia::render('Security/Index'))->name('security');
    Route::middleware('mfa')->group(function () {
        Route::get('/admin', function () {
            abort_unless(request()->user()->getAllPermissions()->contains(fn ($permission) => str_ends_with($permission->name, '.view')), 403);

            return Inertia::render('Admin/Index');
        })->name('admin');
        Route::get('/admin-api/users', [ContactCenterController::class, 'users'])->middleware('can:contact-center.view');
        Route::get('/admin-api/audits', [ContactCenterController::class, 'audits'])->middleware('can:audit-logs.view');
        Route::get('/admin-api/settings', [FoundationController::class, 'settings'])->middleware('can:settings.view');
        Route::put('/admin-api/settings', [FoundationController::class, 'saveSettings'])->middleware('can:settings.update');
        Route::get('/admin-api/accounts', [FoundationController::class, 'users'])->middleware('can:users.view');
        Route::post('/admin-api/accounts', [FoundationController::class, 'saveUser'])->middleware('can:users.manage');
        Route::put('/admin-api/accounts/{id}', [FoundationController::class, 'saveUser'])->middleware('can:users.manage');
        Route::post('/admin-api/accounts/{id}/revoke', [FoundationController::class, 'revoke'])->middleware('can:users.manage');
        Route::get('/admin-api/roles', [FoundationController::class, 'roles'])->middleware('can:roles.view');
        Route::post('/admin-api/roles', [FoundationController::class, 'saveRole'])->middleware('can:roles.manage');
        Route::put('/admin-api/roles/{id}', [FoundationController::class, 'saveRole'])->middleware('can:roles.manage');
        Route::prefix('admin-api/{resource}')->where(['resource' => implode('|', array_keys(ContactCenterController::RESOURCES))])->middleware(DomainPermission::class)->group(function () {
            Route::get('/', [ContactCenterController::class, 'index']);
            Route::get('/{id}', [ContactCenterController::class, 'show']);
            Route::post('/', [ContactCenterController::class, 'store']);
            Route::put('/{id}', [ContactCenterController::class, 'update']);
            Route::delete('/{id}', [ContactCenterController::class, 'destroy']);
        });
    });
});
