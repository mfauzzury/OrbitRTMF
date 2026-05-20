<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RtmfProject;
use App\Models\User;
use App\Services\RtmfMemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RtmfProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'name'        => 'admin',
            'description' => 'Full access',
            'permissions' => ['rtmf.view', 'rtmf.manage'],
        ]);

        $testerRole = Role::create([
            'name'        => 'tester',
            'description' => 'Read-only',
            'permissions' => ['rtmf.view'],
        ]);

        $this->admin = User::create([
            'name'      => 'Admin',
            'email'     => 'admin@test.local',
            'password'  => bcrypt('x'),
            'role'      => 'admin',
            'role_id'   => $adminRole->id,
            'is_active' => true,
        ]);

        $this->tester = User::create([
            'name'      => 'Tester',
            'email'     => 'tester@test.local',
            'password'  => bcrypt('x'),
            'role'      => 'tester',
            'role_id'   => $testerRole->id,
            'is_active' => true,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeProject(array $attrs = []): RtmfProject
    {
        return RtmfProject::create(array_merge([
            'code'       => 'TST' . uniqid(),
            'name'       => 'Test Project',
            'sort_order' => 0,
        ], $attrs));
    }

    private function addMember(RtmfProject $project, User $user, string $role = 'viewer'): void
    {
        DB::table('rtmf_project_users')->insertOrIgnore([
            'project_id' => $project->id,
            'user_id'    => $user->id,
            'role'       => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── Auth guard ───────────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_list_projects(): void
    {
        $this->getJson('/api/rtmf-projects')->assertStatus(401);
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_admin_sees_all_projects_with_admin_role(): void
    {
        $p1 = $this->makeProject(['code' => 'P1', 'name' => 'Project 1']);
        $p2 = $this->makeProject(['code' => 'P2', 'name' => 'Project 2']);

        $res = $this->actingAs($this->admin)->getJson('/api/rtmf-projects');

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->toArray();
        $this->assertContains($p1->id, $ids);
        $this->assertContains($p2->id, $ids);

        foreach ($res->json('data') as $p) {
            $this->assertEquals('admin', $p['myRole']);
        }
    }

    public function test_member_only_sees_their_projects_with_correct_role(): void
    {
        $mine   = $this->makeProject(['code' => 'MINE']);
        $theirs = $this->makeProject(['code' => 'THEIRS']);

        $this->addMember($mine, $this->tester, 'business_analyst');

        $res = $this->actingAs($this->tester)->getJson('/api/rtmf-projects');

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->toArray();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);

        $found = collect($res->json('data'))->firstWhere('id', $mine->id);
        $this->assertEquals('business_analyst', $found['myRole']);
    }

    public function test_non_member_sees_empty_list(): void
    {
        $this->makeProject(['code' => 'NOBODY']);

        $res = $this->actingAs($this->tester)->getJson('/api/rtmf-projects');

        $res->assertOk()->assertJsonCount(0, 'data');
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function test_admin_can_show_project(): void
    {
        $project = $this->makeProject(['code' => 'SHOW', 'name' => 'Show Me']);

        $this->actingAs($this->admin)
            ->getJson("/api/rtmf-projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'SHOW');
    }

    public function test_show_returns_404_for_missing_project(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/rtmf-projects/99999')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    // ── Store ────────────────────────────────────────────────────────────────

    public function test_admin_can_create_project(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/rtmf-projects', [
            'code' => 'NEW',
            'name' => 'New Project',
        ]);

        $res->assertStatus(201)->assertJsonPath('data.code', 'NEW');
        $this->assertDatabaseHas('rtmf_projects', ['code' => 'NEW']);
    }

    public function test_create_requires_code_and_name(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/rtmf-projects', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_create_rejects_duplicate_code(): void
    {
        $this->makeProject(['code' => 'DUP']);

        $this->actingAs($this->admin)
            ->postJson('/api/rtmf-projects', ['code' => 'DUP', 'name' => 'Duplicate'])
            ->assertStatus(422);
    }

    public function test_tester_cannot_create_project(): void
    {
        $this->actingAs($this->tester)
            ->postJson('/api/rtmf-projects', ['code' => 'NOPE', 'name' => 'Nope'])
            ->assertStatus(403);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_project(): void
    {
        $project = $this->makeProject(['code' => 'UPD']);

        $this->actingAs($this->admin)
            ->putJson("/api/rtmf-projects/{$project->id}", ['code' => 'UPD', 'name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_update_returns_404_for_missing_project(): void
    {
        $this->actingAs($this->admin)
            ->putJson('/api/rtmf-projects/99999', ['code' => 'MISS', 'name' => 'X'])
            ->assertStatus(404);
    }

    public function test_tester_cannot_update_project(): void
    {
        $project = $this->makeProject(['code' => 'NOUPD']);

        $this->actingAs($this->tester)
            ->putJson("/api/rtmf-projects/{$project->id}", ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    // ── Destroy ──────────────────────────────────────────────────────────────

    public function test_admin_can_delete_project(): void
    {
        $project = $this->makeProject(['code' => 'DEL']);

        $this->actingAs($this->admin)
            ->deleteJson("/api/rtmf-projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseMissing('rtmf_projects', ['id' => $project->id]);
    }

    public function test_tester_cannot_delete_project(): void
    {
        $project = $this->makeProject(['code' => 'NODEL']);

        $this->actingAs($this->tester)
            ->deleteJson("/api/rtmf-projects/{$project->id}")
            ->assertStatus(403);
    }

    // ── Members list ─────────────────────────────────────────────────────────

    public function test_admin_can_list_members(): void
    {
        $project = $this->makeProject(['code' => 'MBR']);
        $this->addMember($project, $this->tester, 'qa');

        $res = $this->actingAs($this->admin)
            ->getJson("/api/rtmf-projects/{$project->id}/members");

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('qa', $res->json('data.0.projectRole'));
    }

    public function test_members_returns_empty_for_project_with_no_members(): void
    {
        $project = $this->makeProject(['code' => 'EMPTY']);

        $this->actingAs($this->admin)
            ->getJson("/api/rtmf-projects/{$project->id}/members")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_members_returns_404_for_missing_project(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/rtmf-projects/99999/members')
            ->assertStatus(404);
    }

    // ── Add member ───────────────────────────────────────────────────────────

    public function test_admin_can_add_member(): void
    {
        $project = $this->makeProject(['code' => 'ADDM']);

        $newUser = User::create([
            'name'      => 'New Member',
            'email'     => 'newmember@test.local',
            'password'  => bcrypt('x'),
            'role'      => 'QA',
            'is_active' => true,
        ]);

        $res = $this->actingAs($this->admin)
            ->postJson("/api/rtmf-projects/{$project->id}/members", [
                'userId'      => $newUser->id,
                'projectRole' => 'qa',
            ]);

        $res->assertOk()->assertJsonPath('data.success', true);
        $this->assertDatabaseHas('rtmf_project_users', [
            'project_id' => $project->id,
            'user_id'    => $newUser->id,
            'role'       => 'qa',
        ]);
    }

    public function test_add_member_returns_404_when_user_not_found(): void
    {
        $project = $this->makeProject(['code' => 'NOTFND']);

        $this->actingAs($this->admin)
            ->postJson("/api/rtmf-projects/{$project->id}/members", [
                'userId'      => 99999,
                'projectRole' => 'viewer',
            ])
            ->assertStatus(422);
    }

    public function test_add_member_validates_required_fields(): void
    {
        $project = $this->makeProject(['code' => 'VALM']);

        $this->actingAs($this->admin)
            ->postJson("/api/rtmf-projects/{$project->id}/members", [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_add_member_rejects_invalid_role(): void
    {
        $project = $this->makeProject(['code' => 'BADROLE']);

        $this->actingAs($this->admin)
            ->postJson("/api/rtmf-projects/{$project->id}/members", [
                'userId'      => $this->tester->id,
                'projectRole' => 'superuser',
            ])
            ->assertStatus(422);
    }

    public function test_add_member_is_idempotent(): void
    {
        $project = $this->makeProject(['code' => 'IDEM']);
        $this->addMember($project, $this->tester, 'viewer');

        $this->actingAs($this->admin)
            ->postJson("/api/rtmf-projects/{$project->id}/members", [
                'userId'      => $this->tester->id,
                'projectRole' => 'viewer',
            ])
            ->assertOk();

        $count = DB::table('rtmf_project_users')
            ->where('project_id', $project->id)
            ->where('user_id', $this->tester->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_tester_cannot_add_member(): void
    {
        $project = $this->makeProject(['code' => 'NOADD']);

        $this->actingAs($this->tester)
            ->postJson("/api/rtmf-projects/{$project->id}/members", [
                'userId'      => $this->admin->id,
                'projectRole' => 'viewer',
            ])
            ->assertStatus(403);
    }

    // ── Update member ────────────────────────────────────────────────────────

    public function test_admin_can_update_member_role(): void
    {
        $project = $this->makeProject(['code' => 'UPDM']);
        $this->addMember($project, $this->tester, 'viewer');

        $this->actingAs($this->admin)
            ->patchJson("/api/rtmf-projects/{$project->id}/members/{$this->tester->id}", [
                'projectRole' => 'qa',
            ])
            ->assertOk()
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseHas('rtmf_project_users', [
            'project_id' => $project->id,
            'user_id'    => $this->tester->id,
            'role'       => 'qa',
        ]);
    }

    public function test_update_member_rejects_invalid_role(): void
    {
        $project = $this->makeProject(['code' => 'BADUPDM']);
        $this->addMember($project, $this->tester, 'viewer');

        $this->actingAs($this->admin)
            ->patchJson("/api/rtmf-projects/{$project->id}/members/{$this->tester->id}", [
                'projectRole' => 'overlord',
            ])
            ->assertStatus(422);
    }

    public function test_tester_cannot_update_member(): void
    {
        $project = $this->makeProject(['code' => 'NOUPDM']);
        $this->addMember($project, $this->tester, 'viewer');

        $this->actingAs($this->tester)
            ->patchJson("/api/rtmf-projects/{$project->id}/members/{$this->tester->id}", [
                'projectRole' => 'qa',
            ])
            ->assertStatus(403);
    }

    // ── Remove member ────────────────────────────────────────────────────────

    public function test_admin_can_remove_member(): void
    {
        $project = $this->makeProject(['code' => 'RMV']);
        $this->addMember($project, $this->tester, 'viewer');

        $this->actingAs($this->admin)
            ->deleteJson("/api/rtmf-projects/{$project->id}/members/{$this->tester->id}")
            ->assertOk()
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseMissing('rtmf_project_users', [
            'project_id' => $project->id,
            'user_id'    => $this->tester->id,
        ]);
    }

    public function test_remove_non_member_is_safe(): void
    {
        $project = $this->makeProject(['code' => 'SAFERM']);

        $this->actingAs($this->admin)
            ->deleteJson("/api/rtmf-projects/{$project->id}/members/{$this->tester->id}")
            ->assertOk();
    }

    public function test_tester_cannot_remove_member(): void
    {
        $project = $this->makeProject(['code' => 'NORMV']);
        $this->addMember($project, $this->tester, 'viewer');

        $this->actingAs($this->tester)
            ->deleteJson("/api/rtmf-projects/{$project->id}/members/{$this->tester->id}")
            ->assertStatus(403);
    }
}
