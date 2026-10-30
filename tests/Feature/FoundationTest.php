<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $mfa = true): User
    {
        Roles::seed();
        $tenant = Tenant::create(['name' => 'Company']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('Super Admin');
        if ($mfa) {
            $user->forceFill(['two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()), 'two_factor_confirmed_at' => now()])->save();
        }

        return $user;
    }

    public function test_mfa_enrollment_and_required_challenge(): void
    {
        $user = $this->admin(false);
        $this->actingAs($user);
        $this->get('/admin')->assertRedirect('/security');
        $this->getJson('/admin-api/sites')->assertForbidden();
        $this->postJson('/user/two-factor-authentication')->assertStatus(423);
        $this->postJson('/user/confirm-password', ['password' => 'password'])->assertSuccessful();
        $this->postJson('/user/two-factor-authentication')->assertSuccessful();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNotSame(decrypt($user->two_factor_secret), $user->two_factor_secret);
        $this->postJson('/user/confirmed-two-factor-authentication', ['code' => '000000'])->assertUnprocessable();
        $secret = decrypt($user->two_factor_secret);
        $this->postJson('/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertSuccessful();
        $this->get('/admin')->assertOk();
        $recovery = $this->getJson('/user/two-factor-recovery-codes')->assertOk()->json();
        $this->assertCount(8, $recovery);
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->postJson('/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();
        // A code accepted during enrollment cannot be replayed during login.
        $this->postJson('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertUnprocessable();
        $engine = new Google2FA;
        $this->post('/two-factor-challenge', ['code' => $engine->oathTotp($secret, $engine->getTimestamp() + 1)])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'TwoFactorAuthenticationConfirmed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login']);
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery[0]])->assertRedirect('/admin');
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->postJson('/two-factor-challenge', ['recovery_code' => $recovery[0]])->assertUnprocessable();
    }

    public function test_account_management_settings_and_secret_redaction(): void
    {
        $this->actingAs($admin = $this->admin());
        $role = Role::findByName('Agent');
        $site = Site::forceCreate(['tenant_id' => $admin->tenant_id, 'name' => 'HQ', 'timezone' => 'UTC']);
        $data = ['name' => 'New Agent', 'email' => 'new@example.test', 'password' => 'NewPassword123!', 'active' => true, 'restrict_site_access' => true, 'roles' => [$role->id], 'sites' => [$site->id]];
        $id = $this->postJson('/admin-api/accounts', $data)->assertCreated()->assertJsonMissingPath('password')->json('id');
        $agent = User::findOrFail($id);
        $this->assertTrue(Hash::check($data['password'], $agent->password));
        $this->getJson('/admin-api/accounts')->assertOk()->assertJsonMissingPath('0.two_factor_secret');
        $this->putJson('/admin-api/settings', ['display_name' => 'GWO SEVO', 'timezone' => 'Africa/Accra', 'contact_email' => 'office@example.test', 'default_wrapup_seconds' => 45])->assertOk()->assertJsonPath('default_wrapup_seconds', 45);
        $this->getJson('/admin-api/settings')->assertOk()->assertJsonPath('display_name', 'GWO SEVO');
        $this->putJson('/admin-api/accounts/'.$id, array_merge($data, ['password' => null, 'name' => 'Updated Agent']))->assertOk();
        $this->assertTrue(Hash::check($data['password'], $agent->fresh()->password));
        foreach (DB::table('audit_logs')->get() as $audit) {
            $this->assertStringNotContainsString('NewPassword123!', $audit->changes);
            $this->assertStringNotContainsString('two_factor_secret', $audit->changes);
        }
        $this->postJson('/admin-api/accounts/'.$id.'/revoke')->assertNoContent();
        $this->assertSame(3, $agent->fresh()->auth_version);
        $this->actingAs($agent->fresh())->withSession(['auth_version' => 2]);
        $this->getJson('/admin-api/sites')->assertUnauthorized();
    }

    public function test_site_scoping_and_role_escalation_protection(): void
    {
        $this->actingAs($admin = $this->admin());
        $one = Site::forceCreate(['tenant_id' => $admin->tenant_id, 'name' => 'One', 'timezone' => 'UTC']);
        $two = Site::forceCreate(['tenant_id' => $admin->tenant_id, 'name' => 'Two', 'timezone' => 'UTC']);
        $restricted = User::factory()->create(['tenant_id' => $admin->tenant_id, 'restrict_site_access' => true]);
        $restricted->assignRole('Viewer');
        $restricted->sites()->sync([$one->id]);
        $this->actingAs($restricted)->getJson('/admin-api/sites')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $one->id);
        $this->getJson('/admin-api/sites/'.$two->id)->assertNotFound();
        $this->actingAs($admin);
        $custom = $this->postJson('/admin-api/roles', ['name' => 'Site Manager', 'permissions' => ['contact-center.view', 'sites.view', 'teams.view', 'teams.create', 'agents.create', 'agents.view']])->assertCreated()->json();
        $restricted->syncRoles([$custom['id']]);
        $restricted->forceFill(['two_factor_confirmed_at' => now(), 'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey())])->save();
        $this->actingAs($restricted->fresh())->withSession(['auth_version' => $restricted->auth_version]);
        $this->postJson('/admin-api/teams', ['name' => 'Denied', 'site_id' => $two->id])->assertUnprocessable()->assertJsonValidationErrors('site_id');
        $this->postJson('/admin-api/teams', ['name' => 'Allowed', 'site_id' => $one->id])->assertCreated();
        $this->postJson('/admin-api/sites', ['name' => 'Denied', 'timezone' => 'UTC'])->assertForbidden();
        $this->postJson('/admin-api/agents', ['name' => 'Forbidden assignment', 'site_id' => $one->id, 'user_id' => $admin->id])->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->actingAs($admin)->withSession(['auth_version' => $admin->auth_version]);
        $otherAdmin = User::factory()->create(['tenant_id' => $admin->tenant_id]);
        $otherAdmin->assignRole('Administrator');
        $otherAdmin->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->actingAs($otherAdmin)->withSession(['auth_version' => 1]);
        $this->postJson('/admin-api/accounts', ['name' => 'Escalated', 'email' => 'evil@example.test', 'password' => 'Password12345!', 'active' => true, 'restrict_site_access' => false, 'roles' => [Role::findByName('Super Admin')->id], 'sites' => []])->assertForbidden();
        $this->putJson('/admin-api/roles/'.Role::findByName('Administrator')->id, ['name' => 'Changed', 'permissions' => []])->assertNotFound();
    }

    public function test_tenant_admin_endpoints_cannot_cross_tenants_and_inactive_login_is_denied(): void
    {
        $first = $this->admin();
        $second = $this->admin();
        $this->actingAs($first);
        $this->postJson('/admin-api/accounts/'.$second->id.'/revoke')->assertNotFound();
        $this->putJson('/admin-api/accounts/'.$second->id, [])->assertNotFound();
        $this->getJson('/admin-api/accounts')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $first->id);
        $this->post('/logout');
        $first->active = false;
        $first->save();
        $this->postJson('/login', ['email' => $first->email, 'password' => 'password'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_failed_logins_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_mfa_switch_allows_login_for_enrolled_and_unenrolled_administrators(): void
    {
        config(['fortify.mfa_enabled' => false, 'fortify.features' => []]);
        $user = $this->admin(true);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->get('/admin')->assertOk();
        $this->get('/security')->assertInertia(fn ($page) => $page->where('mfaAvailable', false));
        $this->post('/logout');
        $unenrolled = $this->admin(false);
        $this->post('/login', ['email' => $unenrolled->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
        $this->getJson('/admin-api/sites')->assertOk();
        // Turning enforcement back on preserves enrollment and restores the gate.
        config(['fortify.mfa_enabled' => true]);
        $this->get('/admin')->assertRedirect('/security');
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }
}
