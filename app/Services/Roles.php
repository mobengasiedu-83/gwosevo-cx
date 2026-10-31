<?php

namespace App\Services;

use App\Http\Controllers\ContactCenterController;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Roles
{
    public const NAMES = ['Super Admin', 'Administrator', 'Contact Center Manager', 'Supervisor', 'Team Lead', 'Agent', 'QA Officer', 'Reporting User', 'Technical Administrator', 'AI Administrator', 'Viewer'];

    public static function permissions(): array
    {
        $permissions = ['portal.admin', 'portal.supervisor', 'portal.agent', 'contact-center.view', 'contact-center.manage', 'users.view', 'users.manage', 'roles.view', 'roles.manage', 'settings.view', 'settings.update', 'audit-logs.view'];
        foreach (array_keys(ContactCenterController::RESOURCES) as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $verb) {
                $permissions[] = "$resource.$verb";
            }
        }

        return $permissions;
    }

    public static function seed(): void
    {
        foreach (self::permissions() as $name) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (self::NAMES as $name) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            if (in_array($name, ['Super Admin', 'Administrator']) || $role->wasRecentlyCreated) {
                $role->syncPermissions(in_array($name, ['Super Admin', 'Administrator']) ? self::permissions() : array_values(array_filter(self::permissions(), fn ($p) => str_ends_with($p, '.view') && ! in_array($p, ['users.view', 'roles.view', 'audit-logs.view']))));
            }
            if (in_array($name, ['Agent', 'Supervisor', 'Team Lead'])) {
                $role->syncPermissions([$name === 'Agent' ? 'portal.agent' : 'portal.supervisor']);
            }
        }
    }
}
