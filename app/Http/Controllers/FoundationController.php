<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use App\Services\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class FoundationController extends Controller
{
    private function tenant(Request $request): string
    {
        abort_unless($request->user()->tenant_id && ! $request->user()->restrict_site_access, 403, 'Tenant administration requires tenant-wide access.');

        return $request->user()->tenant_id;
    }

    private function allowedRole(Request $request, Role $role): void
    {
        abort_unless(! $role->tenant_id || $role->tenant_id === $request->user()->tenant_id, 403);
        if (! $request->user()->hasRole('Super Admin')) {
            abort_if($role->name === 'Super Admin', 403);
            abort_if($role->permissions->pluck('name')->diff($request->user()->getAllPermissions()->pluck('name'))->isNotEmpty(), 403, 'You cannot grant permissions you do not hold.');
        }
    }

    private function summary(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'active', 'restrict_site_access']) + [
            'roles' => $user->roles->pluck('id'), 'sites' => $user->sites->pluck('id'),
            'mfa_enabled' => (bool) $user->two_factor_confirmed_at,
        ];
    }

    public function users(Request $request)
    {
        return User::where('tenant_id', $this->tenant($request))->orderBy('name')->get()->map(fn ($u) => $this->summary($u));
    }

    public function saveUser(Request $request, ?string $id = null)
    {
        $tenant = $this->tenant($request);
        $user = $id ? User::where('tenant_id', $tenant)->findOrFail($id) : new User;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => [$id ? 'nullable' : 'required', 'string', Password::min(12)->mixedCase()->numbers()],
            'roles' => ['required', 'array', 'min:1'], 'roles.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'id')],
            'active' => ['required', 'boolean'], 'restrict_site_access' => ['required', 'boolean'],
            'sites' => ['present', 'array'], 'sites.*' => ['required', 'distinct', Rule::exists('sites', 'id')->where('tenant_id', $tenant)],
        ]);
        $roles = Role::with('permissions')->whereIn('id', $data['roles'])->get();
        foreach ($roles as $role) {
            $this->allowedRole($request, $role);
        }
        if ($user->exists && $user->hasRole('Super Admin')) {
            abort_unless($request->user()->hasRole('Super Admin'), 403);
        }
        abort_if($user->id === $request->user()->id && (! $data['active'] || $data['restrict_site_access'] || ! $roles->contains('name', 'Super Admin') && $user->hasRole('Super Admin')), 422, 'You cannot disable or demote your own superadmin account.');
        if ($user->exists && $user->hasRole('Super Admin') && (! $data['active'] || ! $roles->contains('name', 'Super Admin'))) {
            abort_unless(User::where('tenant_id', $tenant)->where('active', true)->whereKeyNot($user->id)->role('Super Admin')->exists(), 422, 'Keep at least one active superadmin.');
        }
        DB::transaction(function () use ($request, $data, $user, $roles, $tenant) {
            $before = $user->exists ? $this->summary($user) : null;
            $user->fill(collect($data)->only(['name', 'email'])->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->tenant_id = $tenant;
            $user->active = $data['active'];
            $user->restrict_site_access = $data['restrict_site_access'];
            if ($user->exists) {
                $user->auth_version++;
            }
            $user->save();
            $user->syncRoles($roles);
            $user->sites()->sync($data['sites']);
            $user->unsetRelations();
            Audit::record($request->user(), $before ? 'update' : 'create', 'users', (string) $user->id, ['before' => $before, 'after' => $this->summary($user), 'password_changed' => ! empty($data['password'])]);
            if ($user->id === $request->user()->id) {
                $request->session()->put('auth_version', $user->auth_version);
            }
        });

        return response()->json($this->summary($user), $id ? 200 : 201);
    }

    public function revoke(Request $request, string $id)
    {
        $user = User::where('tenant_id', $this->tenant($request))->findOrFail($id);
        if ($user->hasRole('Super Admin')) {
            abort_unless($request->user()->hasRole('Super Admin'), 403);
        }
        $user->increment('auth_version');
        Audit::record($request->user(), 'revoke_sessions', 'users', (string) $user->id);

        return response()->noContent();
    }

    public function roles(Request $request)
    {
        $tenant = $this->tenant($request);

        return ['roles' => Role::with('permissions:id,name')->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant))->get(), 'permissions' => Roles::permissions()];
    }

    public function saveRole(Request $request, ?string $id = null)
    {
        $tenant = $this->tenant($request);
        $role = $id ? Role::where('tenant_id', $tenant)->findOrFail($id) : new Role(['guard_name' => 'web']);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN _-]+$/u'], 'permissions' => ['present', 'array'], 'permissions.*' => ['required', 'distinct', Rule::in(Roles::permissions())]]);
        abort_if(collect($data['permissions'])->diff($request->user()->getAllPermissions()->pluck('name'))->isNotEmpty(), 403);
        $name = $tenant.':'.$data['name'];
        abort_if(Role::where('name', $name)->where('guard_name', 'web')->when($id, fn ($q) => $q->whereKeyNot($id))->exists(), 422, 'Role name is already used.');
        DB::transaction(function () use ($request, $role, $data, $tenant, $name) {
            $before = $role->exists ? $role->load('permissions')->toArray() : null;
            $role->name = $name;
            $role->tenant_id = $tenant;
            $role->save();
            $role->syncPermissions($data['permissions']);
            Audit::record($request->user(), $before ? 'update' : 'create', 'roles', (string) $role->id, ['before' => $before, 'after' => $role->fresh('permissions')->toArray()]);
            // Existing sessions must reauthenticate after authorization changes.
            User::where('tenant_id', $tenant)->whereHas('roles', fn ($q) => $q->where('roles.id', $role->id))->increment('auth_version');
        });

        return response()->json($role->fresh('permissions'), $id ? 200 : 201);
    }

    public function settings(Request $request)
    {
        $tenant = $request->user()->tenant_id;
        abort_unless($tenant, 403);

        return response()->json(DB::table('tenant_settings')->where('tenant_id', $tenant)->first() ?? ['display_name' => $request->user()->tenant->name, 'timezone' => 'Africa/Accra', 'contact_email' => null, 'default_wrapup_seconds' => 30]);
    }

    public function saveSettings(Request $request)
    {
        $tenant = $this->tenant($request);
        $data = $request->validate(['display_name' => ['required', 'string', 'max:255'], 'timezone' => ['required', 'timezone'], 'contact_email' => ['nullable', 'email', 'max:255'], 'default_wrapup_seconds' => ['required', 'integer', 'between:0,3600']]);
        DB::transaction(function () use ($request, $tenant, $data) {
            $before = DB::table('tenant_settings')->where('tenant_id', $tenant)->first();
            DB::table('tenant_settings')->updateOrInsert(['tenant_id' => $tenant], $data + ['created_at' => $before?->created_at ?? now(), 'updated_at' => now()]);
            Audit::record($request->user(), 'update', 'settings', $tenant, ['before' => $before, 'after' => $data]);
        });

        return $this->settings($request);
    }
}
