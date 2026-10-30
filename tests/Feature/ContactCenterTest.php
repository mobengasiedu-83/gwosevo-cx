<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContactCenterTest extends TestCase
{
    use RefreshDatabase;

    private function account(bool $manage = true): User
    {
        $tenant = Tenant::create(['name' => 'Tenant']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Roles::seed();
        Permission::findOrCreate('contact-center.view', 'web');
        Permission::findOrCreate('contact-center.manage', 'web');
        $role = Role::findOrCreate($manage ? 'Administrator' : 'Viewer', 'web');
        $role->syncPermissions($manage ? Roles::permissions() : ['contact-center.view']);
        $user->assignRole($role);
        if ($manage) {
            $user->forceFill(['two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()), 'two_factor_confirmed_at' => now()])->save();
        }

        return $user;
    }

    private function createRecord(string $resource, array $data): array
    {
        return $this->postJson('/admin-api/'.$resource, $data)->assertCreated()->json();
    }

    private function configuration(): array
    {
        $site = $this->createRecord('sites', ['name' => 'Accra', 'timezone' => 'Africa/Accra']);
        $department = $this->createRecord('departments', ['name' => 'Support', 'site_id' => $site['id']]);
        $team = $this->createRecord('teams', ['name' => 'Team One', 'site_id' => $site['id'], 'department_id' => $department['id']]);
        $skill = $this->createRecord('skills', ['name' => 'English']);
        $extension = $this->createRecord('extensions', ['name' => 'Desk One', 'number' => '1001', 'site_id' => $site['id']]);
        $hours = $this->createRecord('business-hours', ['name' => 'Weekdays', 'timezone' => 'Africa/Accra', 'schedule' => [['day' => 1, 'opens' => '09:00', 'closes' => '17:00']], 'holidays' => ['2026-12-25']]);
        $profile = $this->createRecord('routing-profiles', ['name' => 'Standard', 'business_hours_id' => $hours['id'], 'strategy' => 'skill_based', 'ring_timeout' => 30, 'max_wait' => 300, 'overflow_action' => 'hangup']);
        $agent = $this->createRecord('agents', ['name' => 'Agent One', 'user_id' => auth()->id(), 'site_id' => $site['id'], 'team_id' => $team['id'], 'extension_id' => $extension['id'], 'skills' => [['id' => $skill['id'], 'proficiency' => 4]]]);
        $queue = $this->createRecord('queues', ['name' => 'Support Queue', 'site_id' => $site['id'], 'routing_profile_id' => $profile['id'], 'priority' => 1, 'members' => [['id' => $agent['id'], 'priority' => 1]], 'skills' => [['id' => $skill['id'], 'minimum_proficiency' => 3]]]);

        return compact('site', 'team', 'skill', 'extension', 'hours', 'profile', 'agent', 'queue');
    }

    public function test_complete_configuration_and_audit_history(): void
    {
        $this->actingAs($user = $this->account());
        $records = $this->configuration();
        $queue = $records['queue'];
        $this->getJson('/admin-api/queues/'.$queue['id'])->assertOk()->assertJsonPath('members.0.id', $records['agent']['id'])->assertJsonPath('skills.0.pivot.minimum_proficiency', 3);
        $this->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Index'));
        $this->getJson('/admin-api/audits')->assertOk()->assertJsonPath('total', 9);
        $this->assertDatabaseHas('agent_skills', ['agent_id' => $records['agent']['id'], 'proficiency' => 4]);
        $this->putJson('/admin-api/queues/'.$queue['id'], array_merge($queue, ['members' => [], 'skills' => [], 'active' => false]))->assertOk();
        $this->assertDatabaseCount('queue_members', 0);
        $this->assertDatabaseCount('queue_skills', 0);
        $audit = DB::table('audit_logs')->where('action', 'update')->first();
        $changes = json_decode($audit->changes, true);
        $this->assertCount(1, $changes['before']['members']);
        $this->assertCount(0, $changes['after']['members']);
        $this->deleteJson('/admin-api/queues/'.$queue['id'])->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $user->tenant_id, 'action' => 'delete', 'resource_id' => $queue['id']]);
    }

    public function test_tenant_isolation_and_foreign_reference_validation(): void
    {
        $owner = $this->account();
        $this->actingAs($owner);
        $records = $this->configuration();
        $this->actingAs($other = $this->account());
        $this->getJson('/admin-api/sites')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/admin-api/users')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $other->id);
        $this->getJson('/admin-api/audits')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/admin-api/sites/'.$records['site']['id'])->assertNotFound();
        $this->putJson('/admin-api/sites/'.$records['site']['id'], ['name' => 'Stolen', 'timezone' => 'UTC'])->assertNotFound();
        $this->deleteJson('/admin-api/sites/'.$records['site']['id'])->assertNotFound();
        $this->postJson('/admin-api/teams', ['name' => 'Invalid', 'site_id' => $records['site']['id']])->assertUnprocessable()->assertJsonValidationErrors('site_id');
        $this->postJson('/admin-api/sites', ['name' => 'Own', 'timezone' => 'UTC', 'tenant_id' => $owner->tenant_id])->assertCreated()->assertJsonPath('tenant_id', $other->tenant_id);
    }

    public function test_permissions_and_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->postJson('/admin-api/sites', [])->assertUnauthorized();
        $user = $this->account(false);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->get('/admin')->assertOk();
        $this->getJson('/admin-api/sites')->assertOk();
        $this->postJson('/admin-api/sites', ['name' => 'Blocked', 'timezone' => 'UTC'])->assertForbidden();
        $this->getJson('/admin-api/audits')->assertForbidden();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->actingAs(User::factory()->create());
        $this->get('/admin')->assertForbidden();
    }

    public function test_validation_assignments_and_restricted_deletion(): void
    {
        $this->actingAs($this->account());
        $r = $this->configuration();
        $otherSite = $this->createRecord('sites', ['name' => 'Kumasi', 'timezone' => 'Africa/Accra']);
        $this->postJson('/admin-api/teams', ['name' => 'Wrong site', 'site_id' => $otherSite['id'], 'department_id' => $r['team']['department_id']])->assertUnprocessable()->assertJsonValidationErrors('department_id');
        $this->putJson('/admin-api/extensions/'.$r['extension']['id'], array_merge($r['extension'], ['site_id' => $otherSite['id']]))->assertUnprocessable();
        $this->putJson('/admin-api/agents/'.$r['agent']['id'], array_merge($r['agent'], ['site_id' => $otherSite['id'], 'extension_id' => null, 'team_id' => null]))->assertUnprocessable();
        $this->putJson('/admin-api/queues/'.$r['queue']['id'], array_merge($r['queue'], ['site_id' => $otherSite['id']]))->assertUnprocessable();
        $this->postJson('/admin-api/extensions', ['name' => 'Duplicate', 'site_id' => $r['site']['id'], 'number' => '1001'])->assertUnprocessable();
        $this->deleteJson('/admin-api/extensions/'.$r['extension']['id'])->assertConflict();
        $this->deleteJson('/admin-api/skills/'.$r['skill']['id'])->assertConflict();
        $this->postJson('/admin-api/business-hours', ['name' => 'Invalid', 'timezone' => 'Bogus', 'schedule' => [['day' => 9, 'opens' => '09:00', 'closes' => '17:00']]])->assertUnprocessable();
        $this->postJson('/admin-api/business-hours', ['name' => 'Overnight', 'timezone' => 'UTC', 'schedule' => [['day' => 1, 'opens' => '17:00', 'closes' => '09:00']]])->assertUnprocessable();
        $this->putJson('/admin-api/agents/'.$r['agent']['id'], array_merge($r['agent'], ['skills' => [['id' => $r['skill']['id'], 'proficiency' => 6]]]))->assertUnprocessable();
        $this->assertDatabaseCount('agent_skills', 1);
    }

    public function test_provisioning_command_creates_a_scoped_administrator_without_shared_credentials(): void
    {
        $this->artisan('contact-center:user', ['email' => 'admin@example.com', '--new-tenant' => 'Example', '--name' => 'Admin'])
            ->expectsQuestion('Password (at least 12 characters, mixed case and numbers)', 'ExamplePassword123!')
            ->assertSuccessful();
        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertNotNull($user->tenant_id);
        $this->assertTrue($user->can('contact-center.manage'));
        $this->assertTrue(Hash::check('ExamplePassword123!', $user->password));
        $this->post('/login', ['email' => $user->email, 'password' => 'ExamplePassword123!'])->assertRedirect('/admin');
        $this->get('/admin')->assertRedirect('/security');
    }

    public function test_unique_agent_extension_and_replaced_skill_assignments(): void
    {
        $this->actingAs($user = $this->account());
        $r = $this->configuration();
        $second = User::factory()->create(['tenant_id' => $user->tenant_id]);
        $this->postJson('/admin-api/agents', ['name' => 'Second Agent', 'user_id' => $second->id, 'site_id' => $r['site']['id'], 'extension_id' => $r['extension']['id']])
            ->assertUnprocessable()->assertJsonValidationErrors('extension_id');
        $this->putJson('/admin-api/agents/'.$r['agent']['id'], array_merge($r['agent'], ['skills' => []]))->assertOk();
        $this->assertDatabaseCount('agent_skills', 0);
        $this->putJson('/admin-api/agents/'.$r['agent']['id'], $r['agent'])->assertOk();
        $this->assertDatabaseCount('agent_skills', 0);
    }
}
