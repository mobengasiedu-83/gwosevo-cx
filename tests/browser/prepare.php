<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\Roles;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== storage_path('testing/browser.sqlite')) {
    throw new RuntimeException('Browser fixtures require the isolated testing SQLite database.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
Roles::seed();
$tenant = Tenant::create(['name' => 'Browser Test Organization']);
$user = new User(['name' => 'Browser Administrator', 'email' => 'browser@example.test', 'password' => 'BrowserTest12345!']);
$user->tenant_id = $tenant->id;
$user->save();
$user->assignRole('Super Admin');
echo "Isolated browser fixtures prepared.\n";

$agent = new User(['name' => 'Browser Agent', 'email' => 'agent@example.test', 'password' => 'BrowserTest12345!']);
$agent->tenant_id = $tenant->id;
$agent->save();
$agent->assignRole('Agent');
