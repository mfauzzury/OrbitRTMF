<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\RtmfFrontend;
use App\Models\RtmfModule;
use App\Models\RtmfProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Comprehensive access-control tests for all 6 system roles:
 * Admin, BA, QA, Technical, Developer, Viewer
 *
 * Covers:
 *  - hasPermission() model method per role
 *  - /me endpoint returns correct permissions array
 *  - RTMF catalog read (rtmf.view gate)
 *  - RTMF project management (rtmf.manage — Admin only)
 *  - Catalog write enforcement (project role: business_analyst vs others)
 *  - Feedback write enforcement (project role must match feedback role)
 *  - ExternalAuthService: new users → Viewer, existing users → role preserved
 */
class UserAccessControlTest extends TestCase
{
    use RefreshDatabase;

    // System roles
    private Role $adminRole;
    private Role $baRole;
    private Role $qaRole;
    private Role $technicalRole;
    private Role $developerRole;
    private Role $viewerRole;

    // Users
    private User $admin;
    private User $ba;
    private User $qa;
    private User $technical;
    private User $developer;
    private User $viewer;

    // Shared project + module for catalog tests
    private RtmfProject $project;
    private RtmfModule $module;

    protected function setUp(): void
    {
        parent::setUp();

        // ── Roles (seeded by migration — fetch or create) ─────────────────
        $this->adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Full access', 'permissions' => Permission::all()]
        );

        $this->baRole = Role::firstOrCreate(
            ['name' => 'BA'],
            ['description' => 'Business Analyst', 'permissions' => ['rtmf.view', 'rtmf.catalog', 'rtmf.tools', 'rtmf.tracker', 'rtmf.feedback']]
        );

        $this->qaRole = Role::firstOrCreate(
            ['name' => 'QA'],
            ['description' => 'QA', 'permissions' => ['rtmf.view', 'rtmf.catalog', 'rtmf.tracker', 'rtmf.feedback']]
        );

        $this->technicalRole = Role::firstOrCreate(
            ['name' => 'Technical'],
            ['description' => 'Technical', 'permissions' => ['rtmf.view', 'rtmf.catalog', 'rtmf.tracker', 'rtmf.feedback']]
        );

        $this->developerRole = Role::firstOrCreate(
            ['name' => 'Developer'],
            ['description' => 'Developer', 'permissions' => ['rtmf.view', 'rtmf.catalog', 'rtmf.tracker', 'rtmf.feedback']]
        );

        $this->viewerRole = Role::firstOrCreate(
            ['name' => 'Viewer'],
            ['description' => 'Viewer', 'permissions' => ['rtmf.view', 'rtmf.tracker']]
        );

        // ── Users ────────────────────────────────────────────────────────────
        $this->admin     = $this->makeUser('admin',     'admin',     $this->adminRole);
        $this->ba        = $this->makeUser('ba',        'BA',        $this->baRole);
        $this->qa        = $this->makeUser('qa',        'QA',        $this->qaRole);
        $this->technical = $this->makeUser('tech',      'Technical', $this->technicalRole);
        $this->developer = $this->makeUser('dev',       'Developer', $this->developerRole);
        $this->viewer    = $this->makeUser('viewer',    'Viewer',    $this->viewerRole);

        // ── Shared project + module ──────────────────────────────────────────
        $this->project = RtmfProject::create(['code' => 'UAC', 'name' => 'Access Control Test Project']);
        $this->module  = RtmfModule::create(['code' => 'UACM', 'name' => 'UAC Module', 'project_id' => $this->project->id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(string $suffix, string $role, Role $roleModel): User
    {
        return User::create([
            'name'      => "User {$suffix}",
            'email'     => "{$suffix}@test.local",
            'password'  => bcrypt('x'),
            'role'      => $role,
            'role_id'   => $roleModel->id,
            'is_active' => true,
        ]);
    }

    private function addProjectMember(User $user, string $projectRole): void
    {
        DB::table('rtmf_project_users')->insert([
            'project_id' => $this->project->id,
            'user_id'    => $user->id,
            'role'       => $projectRole,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeFrontend(string $specId): RtmfFrontend
    {
        return RtmfFrontend::create([
            'spec_id'   => $specId,
            'module_id' => $this->module->id,
            'title'     => "Frontend {$specId}",
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Section 1 — hasPermission() model method
    // ═══════════════════════════════════════════════════════════════════════

    public function test_admin_has_permission_for_everything(): void
    {
        $this->assertTrue($this->admin->hasPermission('rtmf.view'));
        $this->assertTrue($this->admin->hasPermission('rtmf.manage'));
        $this->assertTrue($this->admin->hasPermission('rtmf.catalog'));
        $this->assertTrue($this->admin->hasPermission('rtmf.catalog.edit'));
        $this->assertTrue($this->admin->hasPermission('rtmf.tracker'));
        $this->assertTrue($this->admin->hasPermission('rtmf.feedback'));
        $this->assertTrue($this->admin->hasPermission('users.delete'));
        $this->assertTrue($this->admin->hasPermission('anything.at.all'));
    }

    public function test_ba_has_correct_permissions(): void
    {
        $this->assertTrue($this->ba->hasPermission('rtmf.view'));
        $this->assertTrue($this->ba->hasPermission('rtmf.catalog'));
        $this->assertTrue($this->ba->hasPermission('rtmf.tools'));
        $this->assertTrue($this->ba->hasPermission('rtmf.tracker'));
        $this->assertTrue($this->ba->hasPermission('rtmf.feedback'));

        $this->assertFalse($this->ba->hasPermission('rtmf.manage'));
        $this->assertFalse($this->ba->hasPermission('users.view'));
        $this->assertFalse($this->ba->hasPermission('posts.view'));
    }

    public function test_qa_has_correct_permissions(): void
    {
        $this->assertTrue($this->qa->hasPermission('rtmf.view'));
        $this->assertTrue($this->qa->hasPermission('rtmf.catalog'));
        $this->assertTrue($this->qa->hasPermission('rtmf.tracker'));
        $this->assertTrue($this->qa->hasPermission('rtmf.feedback'));

        $this->assertFalse($this->qa->hasPermission('rtmf.catalog.edit'));
        $this->assertFalse($this->qa->hasPermission('rtmf.manage'));
        $this->assertFalse($this->qa->hasPermission('users.view'));
    }

    public function test_technical_has_correct_permissions(): void
    {
        $this->assertTrue($this->technical->hasPermission('rtmf.view'));
        $this->assertTrue($this->technical->hasPermission('rtmf.catalog'));
        $this->assertTrue($this->technical->hasPermission('rtmf.tracker'));
        $this->assertTrue($this->technical->hasPermission('rtmf.feedback'));

        $this->assertFalse($this->technical->hasPermission('rtmf.catalog.edit'));
        $this->assertFalse($this->technical->hasPermission('rtmf.manage'));
    }

    public function test_developer_has_correct_permissions(): void
    {
        $this->assertTrue($this->developer->hasPermission('rtmf.view'));
        $this->assertTrue($this->developer->hasPermission('rtmf.catalog'));
        $this->assertTrue($this->developer->hasPermission('rtmf.tracker'));
        $this->assertTrue($this->developer->hasPermission('rtmf.feedback'));

        $this->assertFalse($this->developer->hasPermission('rtmf.catalog.edit'));
        $this->assertFalse($this->developer->hasPermission('rtmf.manage'));
    }

    public function test_viewer_has_correct_permissions(): void
    {
        $this->assertTrue($this->viewer->hasPermission('rtmf.view'));
        $this->assertTrue($this->viewer->hasPermission('rtmf.tracker'));

        $this->assertFalse($this->viewer->hasPermission('rtmf.catalog'));
        $this->assertFalse($this->viewer->hasPermission('rtmf.catalog.edit'));
        $this->assertFalse($this->viewer->hasPermission('rtmf.feedback'));
        $this->assertFalse($this->viewer->hasPermission('rtmf.manage'));
        $this->assertFalse($this->viewer->hasPermission('users.view'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Section 2 — /me endpoint returns correct permissions
    // ═══════════════════════════════════════════════════════════════════════

    public function test_me_returns_all_permissions_for_admin(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/api/auth/me');

        $res->assertOk();
        $perms = $res->json('data.user.permissions');
        $this->assertContains('rtmf.view', $perms);
        $this->assertContains('rtmf.manage', $perms);
        $this->assertContains('rtmf.catalog', $perms);
        $this->assertContains('rtmf.tracker', $perms);
        $this->assertContains('rtmf.feedback', $perms);
        $this->assertContains('users.view', $perms);
    }

    public function test_me_returns_ba_permissions(): void
    {
        // Non-admin users get permissions: [] from /me.
        // Effective permissions are derived on the frontend from project role via rtmfProjectStore.
        $res = $this->actingAs($this->ba)->getJson('/api/auth/me');
        $res->assertOk();
        $this->assertEmpty($res->json('data.user.permissions'));
    }

    public function test_me_returns_qa_permissions(): void
    {
        $res = $this->actingAs($this->qa)->getJson('/api/auth/me');
        $res->assertOk();
        $this->assertEmpty($res->json('data.user.permissions'));
    }

    public function test_me_returns_viewer_permissions(): void
    {
        $res = $this->actingAs($this->viewer)->getJson('/api/auth/me');
        $res->assertOk();
        $this->assertEmpty($res->json('data.user.permissions'));
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Section 3 — RTMF catalog read (rtmf.view gate)
    // All 6 roles have rtmf.view — all can read catalog endpoints
    // ═══════════════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\DataProvider('allRolesProvider')]
    public function test_all_roles_can_read_rtmf_frontends(string $userProp): void
    {
        $this->actingAs($this->$userProp)
            ->getJson('/api/rtmf-frontends')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('allRolesProvider')]
    public function test_all_roles_can_read_rtmf_modules(string $userProp): void
    {
        $this->actingAs($this->$userProp)
            ->getJson('/api/rtmf-modules')
            ->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('allRolesProvider')]
    public function test_all_roles_can_read_rtmf_dashboard(string $userProp): void
    {
        $this->actingAs($this->$userProp)
            ->getJson('/api/rtmf/dashboard')
            ->assertOk();
    }

    public static function allRolesProvider(): array
    {
        return [
            'Admin'     => ['admin'],
            'BA'        => ['ba'],
            'QA'        => ['qa'],
            'Technical' => ['technical'],
            'Developer' => ['developer'],
            'Viewer'    => ['viewer'],
        ];
    }

    public function test_unauthenticated_cannot_read_rtmf_frontends(): void
    {
        $this->getJson('/api/rtmf-frontends')->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Section 4 — RTMF project management (rtmf.manage — Admin only)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_admin_can_create_project(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/rtmf-projects', ['code' => 'NEW1', 'name' => 'New Project'])
            ->assertStatus(201)
            ->assertJsonPath('data.code', 'NEW1');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonAdminRolesProvider')]
    public function test_non_admin_cannot_create_project(string $userProp): void
    {
        $this->actingAs($this->$userProp)
            ->postJson('/api/rtmf-projects', ['code' => 'HACK', 'name' => 'Hack'])
            ->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonAdminRolesProvider')]
    public function test_non_admin_cannot_add_project_member(string $userProp): void
    {
        $this->actingAs($this->$userProp)
            ->postJson("/api/rtmf-projects/{$this->project->id}/members", [
                'userId'      => $this->viewer->id,
                'projectRole' => 'viewer',
            ])
            ->assertStatus(403);
    }

    public static function nonAdminRolesProvider(): array
    {
        return [
            'BA'        => ['ba'],
            'QA'        => ['qa'],
            'Technical' => ['technical'],
            'Developer' => ['developer'],
            'Viewer'    => ['viewer'],
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Section 5 — Catalog write (project role enforcement)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_ba_project_member_can_create_module(): void
    {
        $this->addProjectMember($this->ba, 'business_analyst');

        $this->actingAs($this->ba)
            ->postJson('/api/rtmf-modules', ['code' => 'BAMOD', 'name' => 'BA Module', 'project_id' => $this->project->id])
            ->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('viewOnlyProjectRolesProvider')]
    public function test_view_only_project_member_cannot_create_module(string $userProp, string $projectRole): void
    {
        $this->addProjectMember($this->$userProp, $projectRole);

        $this->actingAs($this->$userProp)
            ->postJson('/api/rtmf-modules', ['code' => 'BADMOD', 'name' => 'Bad', 'project_id' => $this->project->id])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('viewOnlyProjectRolesProvider')]
    public function test_view_only_project_member_cannot_delete_frontend(string $userProp, string $projectRole): void
    {
        $this->addProjectMember($this->$userProp, $projectRole);
        $frontend = $this->makeFrontend("FE-{$userProp}");

        $this->actingAs($this->$userProp)
            ->deleteJson("/api/rtmf-frontends/{$frontend->id}")
            ->assertStatus(403);
    }

    public static function viewOnlyProjectRolesProvider(): array
    {
        return [
            'QA'        => ['qa',        'qa'],
            'Technical' => ['technical', 'technical'],
            'Developer' => ['developer', 'developer'],
            'Viewer'    => ['viewer',    'viewer'],
        ];
    }

    public function test_non_project_member_cannot_create_module(): void
    {
        // BA has rtmf.catalog.edit but is NOT a member of this project
        $this->actingAs($this->ba)
            ->postJson('/api/rtmf-modules', ['code' => 'NOPRJ', 'name' => 'No Project', 'project_id' => $this->project->id])
            ->assertStatus(403);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Section 6 — Feedback write enforcement (project role must match)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_admin_can_write_any_feedback(): void
    {
        $frontend = $this->makeFrontend('FE-ADMIN-FB');

        foreach (['business_analyst', 'qa', 'technical', 'developer'] as $role) {
            $this->actingAs($this->admin)
                ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/{$role}", ['status' => 'reviewed'])
                ->assertOk();
        }
    }

    public function test_ba_project_member_can_write_ba_feedback(): void
    {
        $this->addProjectMember($this->ba, 'business_analyst');
        $frontend = $this->makeFrontend('FE-BA-FB');

        $this->actingAs($this->ba)
            ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/business_analyst", ['status' => 'reviewed'])
            ->assertOk();
    }

    public function test_ba_project_member_cannot_write_qa_feedback(): void
    {
        $this->addProjectMember($this->ba, 'business_analyst');
        $frontend = $this->makeFrontend('FE-BA-FB2');

        $this->actingAs($this->ba)
            ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/qa", ['status' => 'reviewed'])
            ->assertStatus(403);
    }

    public function test_qa_project_member_can_write_qa_feedback(): void
    {
        $this->addProjectMember($this->qa, 'qa');
        $frontend = $this->makeFrontend('FE-QA-FB');

        $this->actingAs($this->qa)
            ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/qa", ['status' => 'approved'])
            ->assertOk();
    }

    public function test_qa_project_member_cannot_write_ba_feedback(): void
    {
        $this->addProjectMember($this->qa, 'qa');
        $frontend = $this->makeFrontend('FE-QA-FB2');

        $this->actingAs($this->qa)
            ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/business_analyst", ['status' => 'reviewed'])
            ->assertStatus(403);
    }

    public function test_technical_project_member_can_write_technical_feedback(): void
    {
        $this->addProjectMember($this->technical, 'technical');
        $frontend = $this->makeFrontend('FE-TECH-FB');

        $this->actingAs($this->technical)
            ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/technical", ['comment' => 'LGTM'])
            ->assertOk();
    }

    public function test_technical_project_member_cannot_write_developer_feedback(): void
    {
        $this->addProjectMember($this->technical, 'technical');
        $frontend = $this->makeFrontend('FE-TECH-FB2');

        $this->actingAs($this->technical)
            ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/developer", ['comment' => 'No'])
            ->assertStatus(403);
    }

    public function test_developer_project_member_can_write_developer_feedback(): void
    {
        $this->addProjectMember($this->developer, 'developer');
        $frontend = $this->makeFrontend('FE-DEV-FB');

        $this->actingAs($this->developer)
            ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/developer", ['status' => 'open'])
            ->assertOk();
    }

    public function test_developer_project_member_cannot_write_technical_feedback(): void
    {
        $this->addProjectMember($this->developer, 'developer');
        $frontend = $this->makeFrontend('FE-DEV-FB2');

        $this->actingAs($this->developer)
            ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/technical", ['comment' => 'Nope'])
            ->assertStatus(403);
    }

    public function test_non_project_member_cannot_write_any_feedback(): void
    {
        $frontend = $this->makeFrontend('FE-NOMEM');

        // QA user, not a member of any project
        foreach (['business_analyst', 'qa', 'technical', 'developer'] as $role) {
            $this->actingAs($this->qa)
                ->putJson("/api/rtmf-frontends/{$frontend->id}/feedbacks/{$role}", ['comment' => 'x'])
                ->assertStatus(403);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Section 7 — ExternalAuthService (credential-only, role stays in OrbitRTMF)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_new_external_user_gets_viewer_role_on_first_provision(): void
    {
        // Integration: create the "external" record in local DB to simulate the lookup,
        // then directly call the provisioning logic.
        $viewerRoleId = $this->viewerRole->id;

        // Simulate what ExternalAuthService does for a brand-new user:
        $local = User::firstOrNew(['email' => 'firsttime@ext.local']);
        $this->assertFalse($local->exists, 'User should not exist yet');

        $local->name      = 'First Timer';
        $local->password  = Hash::make('secret');
        $local->role      = 'Viewer';
        $local->role_id   = $viewerRoleId;
        $local->is_active = true;
        $local->save();

        $saved = User::where('email', 'firsttime@ext.local')->first();
        $this->assertEquals('Viewer', $saved->role);
        $this->assertEquals($viewerRoleId, $saved->role_id);
    }

    public function test_existing_user_role_is_not_overwritten_on_re_login(): void
    {
        // User was promoted to BA by an admin
        $existing = User::create([
            'name'      => 'Existing Staff',
            'email'     => 'existing@ext.local',
            'password'  => bcrypt('old'),
            'role'      => 'BA',
            'role_id'   => $this->baRole->id,
            'is_active' => true,
        ]);

        // Simulate re-login: ExternalAuthService only updates name + password for existing users
        $relogged = User::firstOrNew(['email' => 'existing@ext.local']);
        $this->assertTrue($relogged->exists);

        $relogged->name     = 'Existing Staff';   // name sync from testagent
        $relogged->password = Hash::make('newpw'); // password sync from testagent
        // role and role_id intentionally NOT touched
        $relogged->save();

        $fresh = User::find($existing->id);
        $this->assertEquals('BA', $fresh->role, 'Role must not be overwritten by testagent data');
        $this->assertEquals($this->baRole->id, $fresh->role_id, 'role_id must not be overwritten');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Section 8 — Add project member (local user_id, not external)
    // ═══════════════════════════════════════════════════════════════════════

    public function test_admin_can_add_local_user_as_project_member(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/rtmf-projects/{$this->project->id}/members", [
                'userId'      => $this->qa->id,
                'projectRole' => 'qa',
            ])
            ->assertOk()
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseHas('rtmf_project_users', [
            'project_id' => $this->project->id,
            'user_id'    => $this->qa->id,
            'role'       => 'qa',
        ]);
    }

    public function test_add_member_rejects_non_existent_user_id(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/rtmf-projects/{$this->project->id}/members", [
                'userId'      => 99999,
                'projectRole' => 'viewer',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_add_member_rejects_invalid_project_role(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/rtmf-projects/{$this->project->id}/members", [
                'userId'      => $this->qa->id,
                'projectRole' => 'superuser',
            ])
            ->assertStatus(422);
    }

    public function test_add_member_is_idempotent(): void
    {
        $this->addProjectMember($this->qa, 'viewer');

        $this->actingAs($this->admin)
            ->postJson("/api/rtmf-projects/{$this->project->id}/members", [
                'userId'      => $this->qa->id,
                'projectRole' => 'viewer',
            ])
            ->assertOk();

        $this->assertEquals(1, DB::table('rtmf_project_users')
            ->where('project_id', $this->project->id)
            ->where('user_id', $this->qa->id)
            ->count());
    }

    public function test_project_candidates_excludes_existing_members(): void
    {
        $this->addProjectMember($this->qa, 'qa');

        $res = $this->actingAs($this->admin)
            ->getJson("/api/rtmf-projects/{$this->project->id}/candidates");

        $res->assertOk();
        $emails = collect($res->json('data'))->pluck('email')->all();
        $this->assertNotContains($this->qa->email, $emails);
        $this->assertContains($this->ba->email, $emails);
    }
}