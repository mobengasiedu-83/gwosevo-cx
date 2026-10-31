<?php

namespace Tests\Feature;

use App\Models\Extension;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Navigation;
use App\Services\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        Roles::seed();
        $user = User::factory()->create(['tenant_id' => Tenant::create(['name' => 'Company'])->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_complete_catalogue_preserves_specification_counts(): void
    {
        $admin = $this->user('Super Admin');
        $groups = Navigation::catalogue($admin, 'admin');
        $this->assertCount(17, $groups);
        $this->assertCount(237, collect($groups)->flatMap(fn ($g) => $g['items']));
        $this->assertCount(6, collect($groups)->pluck('section')->unique());
        $this->assertCount(9, Navigation::catalogue($admin, 'supervisor'));
        $this->assertCount(10, Navigation::catalogue($admin, 'agent'));
    }

    public function test_administration_catalogue_matches_the_seventeen_menu_specification(): void
    {
        $groups = collect(Navigation::catalogue($this->user('Super Admin'), 'admin'));
        $this->assertSame([
            'Dashboard & Monitoring' => 'Overview',
            'Extensions & Devices' => 'PBX & Telephony',
            'Call Routing & Trunks' => 'PBX & Telephony',
            'IVR & Incoming Call Tools' => 'PBX & Telephony',
            'Call Center Management' => 'Contact Center',
            'AI Contact Center' => 'Contact Center',
            'Telephony Applications' => 'PBX & Telephony',
            'Class of Service & Policies' => 'PBX & Telephony',
            'Omnichannel & CRM' => 'Contact Center',
            'Campaigns & Outbound Dialer' => 'Contact Center',
            'Reports & Analytics' => 'Analytics & Media',
            'Recordings & Media' => 'Analytics & Media',
            'System & Infrastructure' => 'Operations & Security',
            'Security & Access Control' => 'Operations & Security',
            'Emergency & Compliance' => 'Operations & Security',
            'Administration & Configuration' => 'Platform Administration',
            'Developer & Integration Tools' => 'Platform Administration',
        ], $groups->pluck('section', 'title')->all());
        $this->assertSame([
            'Overview' => 1,
            'PBX & Telephony' => 5,
            'Contact Center' => 4,
            'Analytics & Media' => 2,
            'Operations & Security' => 3,
            'Platform Administration' => 2,
        ], $groups->countBy('section')->all());
        $items = $groups->flatMap(fn ($group) => $group['items']);
        $this->assertTrue($items->every(fn ($item) => filled($item['id']) && filled($item['title']) && filled($item['url'])));
        $this->assertTrue($items->where('status', 'available')->every(fn ($item) => filled($item['resource'])));
    }

    public function test_two_factor_login_and_shared_home_follow_the_assigned_workspace(): void
    {
        $agent = $this->user('Agent');
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
        $agent->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now()])->save();

        $this->post('/login', ['email' => $agent->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect('/agent');
        $this->assertAuthenticatedAs($agent);
        $this->get('/agent')->assertOk()->assertInertia(fn ($page) => $page->where('home', '/agent'));
        $this->get('/security')->assertOk()->assertInertia(fn ($page) => $page->where('home', '/agent'));

        $admin = $this->user('Super Admin');
        config(['fortify.mfa_enabled' => false]);
        $this->actingAs($admin);
        $this->get('/admin')->assertOk()->assertInertia(fn ($page) => $page->where('home', '/admin'));
    }

    public function test_unified_search_respects_module_permissions_and_site_scope(): void
    {
        config(['fortify.mfa_enabled' => false]);
        $admin = $this->user('Super Admin');
        $tenant = $admin->tenant_id;
        $one = Site::forceCreate(['tenant_id' => $tenant, 'name' => 'Harbour One', 'timezone' => 'UTC']);
        $two = Site::forceCreate(['tenant_id' => $tenant, 'name' => 'Harbour Two', 'timezone' => 'UTC']);
        Extension::forceCreate(['tenant_id' => $tenant, 'site_id' => $two->id, 'name' => 'Harbour Line', 'number' => '7001']);
        User::factory()->create(['tenant_id' => $tenant, 'name' => 'Harbour Operator']);

        $this->actingAs($admin);
        $records = collect($this->getJson('/admin-api/search?q=Harbour')->assertOk()->json('records'));
        $this->assertEqualsCanonicalizing(['sites', 'sites', 'extensions', 'accounts'], $records->pluck('type')->all());
        $this->assertTrue($records->contains(fn ($record) => $record['url'] === url('/admin').'?module=extensions'));
        $this->assertTrue($records->firstWhere('type', 'extensions')['subtitle'] === '7001');
        $this->getJson('/admin-api/search?q=H')->assertUnprocessable();

        $restricted = User::factory()->create(['tenant_id' => $tenant, 'restrict_site_access' => true]);
        $restricted->assignRole('Viewer');
        $restricted->sites()->sync([$one->id]);
        $this->actingAs($restricted);
        $records = collect($this->getJson('/admin-api/search?q=Harbour')->assertOk()->json('records'));
        $this->assertSame(['Harbour One'], $records->pluck('title')->all());

        $this->actingAs($admin);
        config(['navigation.disabled_modules' => ['extensions']]);
        $records = collect($this->getJson('/admin-api/search?q=Harbour')->assertOk()->json('records'));
        $this->assertNotContains('extensions', $records->pluck('type')->all());
        $this->getJson('/admin-api/extensions')->assertNotFound();

        $outsider = User::factory()->create(['tenant_id' => $tenant]);
        $this->actingAs($outsider)->getJson('/admin-api/search?q=Harbour')->assertForbidden();
    }

    public function test_portal_routes_enforce_roles_and_login_lands_in_assigned_workspace(): void
    {
        $agent = $this->user('Agent');
        $this->post('/login', ['email' => $agent->email, 'password' => 'password'])->assertRedirect('/agent');
        $this->get('/agent')->assertOk()->assertInertia(fn ($p) => $p->component('Portal/Index')->where('portal', 'agent')->has('navigation', 10));
        $this->get('/admin')->assertForbidden();
        $this->get('/supervisor')->assertForbidden();
        $this->get('/portal/admin/routing/trunk-management')->assertForbidden();
        $this->getJson('/admin-api/accounts')->assertForbidden();
        $this->get('/portal/agent/agent-workspace/dialpad')->assertOk()->assertInertia(fn ($p) => $p->where('selected.status', 'planned'));
        $this->post('/logout');
        $supervisor = $this->user('Supervisor');
        $this->post('/login', ['email' => $supervisor->email, 'password' => 'password'])->assertRedirect('/supervisor');
        $this->get('/supervisor')->assertOk();
        $this->get('/admin')->assertForbidden();
    }

    public function test_permissions_filter_working_items_and_disabled_modules_block_direct_access(): void
    {
        config(['fortify.mfa_enabled' => false]);
        $admin = $this->user('Super Admin');
        $this->actingAs($admin);
        $this->get('/admin?module=extensions')->assertOk()->assertInertia(fn ($p) => $p->where('selectedModule', 'extensions'));
        $this->get('/portal/admin/extensions/extension-management')->assertRedirect('/admin?module=extensions');
        $this->get('/portal/admin/ai/ai-voice-agents')->assertOk()->assertInertia(fn ($p) => $p->where('selected.status', 'planned'));
        config(['navigation.disabled_modules' => ['ai', 'extensions', 'security']]);
        $groups = Navigation::catalogue($admin, 'admin');
        $this->assertNotContains('ai', array_column($groups, 'id'));
        $this->get('/portal/admin/ai/ai-voice-agents')->assertNotFound();
        $this->get('/admin?module=extensions')->assertForbidden();
        $this->getJson('/admin-api/extensions')->assertNotFound();
        $this->postJson('/admin-api/extensions', [])->assertNotFound();
        $this->getJson('/admin-api/accounts')->assertNotFound();
        $this->get('/portal/admin/ai/not-a-real-entry')->assertNotFound();
        $viewer = $this->user('Viewer');
        $titles = collect(Navigation::catalogue($viewer, 'admin'))->flatMap(fn ($g) => $g['items'])->pluck('title');
        $this->assertNotContains('API Credentials', $titles);
        $this->assertNotContains('Users', $titles);
    }

    /**
     * A root-relative client visit escapes a deployment mounted in a
     * sub-directory (the request never reaches Laravel and the web server
     * answers with its own 404), so every client navigation has to be built
     * from the shared base URL.
     */
    public function test_client_router_calls_are_built_from_the_shared_base_url(): void
    {
        $offenders = collect(File::allFiles(resource_path('js')))
            ->filter(fn ($file) => in_array($file->getExtension(), ['ts', 'vue'], true))
            ->filter(fn ($file) => preg_match('/router\.(get|post|put|patch|delete|visit)\(\s*["\'`]\//', $file->getContents()))
            ->map(fn ($file) => str_replace(base_path().'/', '', $file->getPathname()))
            ->values()
            ->all();

        $this->assertSame([], $offenders, 'Client router calls must use the shared base URL helper instead of a root-relative path.');
    }
}
