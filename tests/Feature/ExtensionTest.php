<?php

namespace Tests\Feature;

use App\Models\Extension;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function account(): User
    {
        $tenant = Tenant::create(['name' => 'Tenant']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Roles::seed();
        Permission::findOrCreate('contact-center.view', 'web');
        Permission::findOrCreate('contact-center.manage', 'web');
        $role = Role::findOrCreate('Administrator', 'web');
        $role->syncPermissions(Roles::permissions());
        $user->assignRole($role);
        $user->forceFill(['two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()), 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function site(): array
    {
        return $this->postJson('/admin-api/sites', ['name' => 'Accra', 'timezone' => 'Africa/Accra'])->assertCreated()->json();
    }

    /**
     * A complete endpoint payload covering every editor tab.
     */
    private function endpoint(array $site, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Front Desk',
            'number' => '1001',
            'site_id' => $site['id'],
            'active' => true,
            'class_of_service' => 'internal',
            'features_password' => 'FeaturePass1',
            'email_address' => 'frontdesk@example.com',
            'internal_cid_name' => 'Front Desk',
            'internal_cid_number' => '1001',
            'external_cid_name' => 'Acme Support',
            'external_cid_number' => '+15550100',
            'emergency_cid_name' => 'Acme Support',
            'emergency_cid_number' => '+15550111',
            'account_code' => 'ACC-1001',
            'language' => 'fr',
            'technology' => 'pjsip',
            'user_device' => 'desk-1001',
            'device_password' => 'EndpointPass1',
            'device_description' => 'Lobby phone',
            'profile' => 'default',
            'max_contacts' => 3,
            'codecs' => 'ulaw,alaw',
            'dtmf_mode' => 'rfc4733',
            'device_emergency_cid_name' => 'Acme Support',
            'device_emergency_cid_number' => '+15550111',
            'dispatchable_location' => 'HQ floor 1',
            'deny' => '0.0.0.0/0',
            'permit' => '10.0.0.0/8',
            'ring_device' => true,
            'webrtc_client' => false,
            'voicemail_enabled' => true,
            'voicemail_mailbox' => '1001',
            'voicemail_password' => '1234',
            'voicemail_email' => 'frontdesk@example.com',
            'voicemail_options' => ['attach', 'saycid'],
            'recording_mode' => 'always',
            'call_group' => '1,2',
            'pickup_group' => '3',
            'transport' => 'tls',
            'max_call_duration' => 45,
            'call_waiting' => false,
            'follow_me_enabled' => true,
            'follow_me' => [['number' => '5551234', 'timeout' => 25]],
            'incoming_routes' => [['did' => '+15551234567', 'description' => 'Main line']],
            'contact_company' => 'Acme',
            'contact_position' => 'Reception',
            'contact_phone' => '+15550000',
            'contact_mobile' => '+15550001',
            'contact_notes' => 'Dial 9 for reception.',
        ], $overrides);
    }

    public function test_extension_screen_renders_the_tabbed_editor_with_options(): void
    {
        $this->actingAs($this->account());

        $this->get('/admin?module=extensions')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Admin/Extensions')
            ->where('selectedModule', 'extensions')
            ->has('options.technologies', 4)
            ->has('options.classes', 6)
            ->has('options.voicemailOptions', 4));
    }

    public function test_extension_persists_endpoint_voicemail_and_routing_configuration(): void
    {
        $this->actingAs($this->account());
        $site = $this->site();

        $extension = $this->postJson('/admin-api/extensions', $this->endpoint($site))->assertCreated()->json();

        $this->assertSame('pjsip', $extension['technology']);
        $this->assertSame('desk-1001', $extension['user_device']);
        $this->assertTrue($extension['ring_device']);
        $this->assertFalse($extension['call_waiting']);
        $this->assertFalse($extension['webrtc_client']);
        $this->assertSame('always', $extension['recording_mode']);
        $this->assertSame(45, $extension['max_call_duration']);
        $this->assertSame(['attach', 'saycid'], $extension['voicemail_options']);
        $this->assertSame('5551234', $extension['follow_me'][0]['number']);
        $this->assertSame('+15551234567', $extension['incoming_routes'][0]['did']);
        $this->assertTrue($extension['has_features_password']);
        $this->assertTrue($extension['has_device_password']);
        $this->assertTrue($extension['has_voicemail_password']);
        foreach (['features_password', 'device_password', 'voicemail_password'] as $secret) {
            $this->assertArrayNotHasKey($secret, $extension);
        }

        $stored = Extension::findOrFail($extension['id']);
        $this->assertSame('EndpointPass1', $stored->device_password);
        $this->assertSame('FeaturePass1', $stored->features_password);
        $this->assertSame('1234', $stored->voicemail_password);
        $this->assertNotSame('EndpointPass1', DB::table('extensions')->where('id', $stored->id)->value('device_password'));
        $this->assertDatabaseHas('audit_logs', ['resource' => 'extensions', 'resource_id' => $stored->id, 'action' => 'create']);
    }

    public function test_blank_secrets_keep_the_stored_values_on_update(): void
    {
        $this->actingAs($this->account());
        $site = $this->site();
        $extension = $this->postJson('/admin-api/extensions', $this->endpoint($site))->assertCreated()->json();

        $updated = $this->putJson('/admin-api/extensions/'.$extension['id'], $this->endpoint($site, [
            'name' => 'Front Desk Two',
            'number' => '1002',
            'user_device' => '',
            'features_password' => '',
            'device_password' => '',
            'voicemail_password' => '',
            'voicemail_options' => [],
            'follow_me' => [],
            'incoming_routes' => [],
        ]))->assertOk()->json();

        $this->assertSame('Front Desk Two', $updated['name']);
        $this->assertSame('1002', $updated['user_device']);
        $this->assertSame([], $updated['voicemail_options']);
        $this->assertSame([], $updated['follow_me']);
        $this->assertSame([], $updated['incoming_routes']);
        $this->assertTrue($updated['has_device_password']);
        $this->assertTrue($updated['has_features_password']);
        $this->assertTrue($updated['has_voicemail_password']);

        $stored = Extension::findOrFail($updated['id']);
        $this->assertSame('EndpointPass1', $stored->device_password);
        $this->assertSame('FeaturePass1', $stored->features_password);
        $this->assertSame('1234', $stored->voicemail_password);
    }

    public function test_empty_repeater_rows_are_discarded_before_validation(): void
    {
        $this->actingAs($this->account());
        $site = $this->site();

        $extension = $this->postJson('/admin-api/extensions', $this->endpoint($site, [
            'follow_me' => [
                ['number' => '5551234', 'timeout' => 20],
                ['number' => '', 'timeout' => 20],
            ],
            'incoming_routes' => [
                ['did' => '+15551234567', 'description' => 'Main line'],
                ['did' => '', 'description' => ''],
            ],
        ]))->assertCreated()->json();

        $this->assertCount(1, $extension['follow_me']);
        $this->assertCount(1, $extension['incoming_routes']);
    }

    public function test_device_technology_requires_an_endpoint_password(): void
    {
        $this->actingAs($this->account());
        $site = $this->site();

        $this->postJson('/admin-api/extensions', $this->endpoint($site, ['device_password' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device_password');

        $virtual = $this->postJson('/admin-api/extensions', $this->endpoint($site, [
            'technology' => 'none',
            'user_device' => null,
            'device_password' => null,
        ]))->assertCreated()->json();

        $this->assertSame('none', $virtual['technology']);
        $this->assertNull($virtual['user_device']);
        $this->assertFalse($virtual['has_device_password']);
    }

    public function test_extension_number_and_user_device_are_unique_per_tenant(): void
    {
        $this->actingAs($this->account());
        $site = $this->site();
        $this->postJson('/admin-api/extensions', $this->endpoint($site))->assertCreated();

        $this->postJson('/admin-api/extensions', $this->endpoint($site, ['name' => 'Duplicate Number', 'user_device' => 'desk-2002']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('number');

        $this->postJson('/admin-api/extensions', $this->endpoint($site, ['name' => 'Duplicate Device', 'number' => '2002']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_device');
    }

    public function test_extension_status_toggle_is_audited_and_requires_update_permission(): void
    {
        $admin = $this->account();
        $this->actingAs($admin);
        $site = $this->site();
        $extension = $this->postJson('/admin-api/extensions', $this->endpoint($site))->assertCreated()->json();

        $this->patchJson('/admin-api/extensions/'.$extension['id'].'/status', ['active' => false])
            ->assertOk()
            ->assertJsonPath('active', false)
            ->assertJsonMissingPath('device_password');
        $this->assertFalse(Extension::findOrFail($extension['id'])->active);
        $this->assertDatabaseHas('audit_logs', ['resource' => 'extensions', 'resource_id' => $extension['id'], 'action' => 'deactivate']);

        $this->patchJson('/admin-api/extensions/'.$extension['id'].'/status', ['active' => true])
            ->assertOk()
            ->assertJsonPath('active', true);
        $this->assertTrue(Extension::findOrFail($extension['id'])->active);
        $this->assertDatabaseHas('audit_logs', ['resource' => 'extensions', 'resource_id' => $extension['id'], 'action' => 'activate']);

        // Activating is an update: read-only accounts keep viewing rights only.
        $viewer = User::factory()->create(['tenant_id' => $admin->tenant_id]);
        $viewer->assignRole('Viewer');
        $viewer->forceFill(['two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($viewer)->withSession(['auth_version' => $viewer->auth_version])
            ->patchJson('/admin-api/extensions/'.$extension['id'].'/status', ['active' => false])
            ->assertForbidden();
        $this->assertTrue(Extension::findOrFail($extension['id'])->active);
    }

    public function test_extension_search_matches_number_name_and_device(): void
    {
        $this->actingAs($this->account());
        $site = $this->site();
        $this->postJson('/admin-api/extensions', $this->endpoint($site))->assertCreated();
        $this->postJson('/admin-api/extensions', $this->endpoint($site, [
            'name' => 'Lab Phone',
            'number' => '2002',
            'user_device' => 'lab-2002',
            'device_description' => 'Lab desk',
        ]))->assertCreated();

        $this->getJson('/admin-api/extensions')->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.number', '1001')
            ->assertJsonPath('data.1.number', '2002');
        $this->getJson('/admin-api/extensions?q=2002')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Lab Phone');
        $this->getJson('/admin-api/extensions?q=lab-')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.number', '2002');
        $this->getJson('/admin-api/extensions?q=Front')->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.number', '1001');
    }
}
