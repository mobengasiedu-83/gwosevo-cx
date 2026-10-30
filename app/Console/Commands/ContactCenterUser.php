<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit;
use App\Services\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class ContactCenterUser extends Command
{
    protected $signature = 'contact-center:user {email} {--tenant= : Existing tenant ULID} {--new-tenant= : Name for a new tenant} {--name=} {--role=Administrator : Super Admin, Administrator, Viewer or Agent}';

    protected $description = 'Create a tenant-scoped account using an interactive secret password prompt';

    public function handle(): int
    {
        $role = $this->option('role');
        if (! in_array($role, ['Super Admin', 'Administrator', 'Viewer', 'Agent']) || (bool) $this->option('tenant') === (bool) $this->option('new-tenant')) {
            $this->error('Choose one of --tenant or --new-tenant and a supported role.');

            return self::FAILURE;
        }
        $data = ['email' => $this->argument('email'), 'name' => $this->option('name') ?: $this->ask('Name'), 'password' => $this->secret('Password (at least 12 characters, mixed case and numbers)')];
        $validator = Validator::make($data, ['email' => 'required|email|unique:users,email', 'name' => 'required|string|max:255', 'password' => ['required', Password::min(12)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data, $role) {
            $tenant = $this->option('tenant') ? Tenant::findOrFail($this->option('tenant')) : Tenant::create(['name' => $this->option('new-tenant')]);
            Roles::seed();
            $assignedRole = Role::findByName($role, 'web');
            $user = new User($data);
            $user->tenant_id = $tenant->id;
            $user->save();
            $user->assignRole($assignedRole);
            Audit::record($user, 'provision_account', 'users', (string) $user->id, ['role' => $role]);
            $this->info('Account created. Tenant: '.$tenant->id);
        });

        return self::SUCCESS;
    }
}
