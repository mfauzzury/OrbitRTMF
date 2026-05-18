<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\RtmfFrontend;
use App\Models\RtmfModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RtmfFrontendPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create([
            'name' => 'admin',
            'description' => 'Full access',
            'permissions' => ['rtmf.view', 'rtmf.manage'],
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);
    }

    public function test_rtmf_frontend_index_returns_different_rows_per_page(): void
    {
        $module = RtmfModule::create([
            'code' => 'TST',
            'name' => 'Test Module',
            'sort_order' => 1,
        ]);

        for ($i = 1; $i <= 30; $i++) {
            RtmfFrontend::create([
                'spec_id' => sprintf('TST-PG-%02d', $i),
                'module_id' => $module->id,
                'tab_code' => sprintf('TST-PG-%02d', $i),
                'title' => "Page item {$i}",
            ]);
        }

        $this->actingAs($this->admin);

        $page1 = $this->getJson('/api/rtmf-frontends?page=1&limit=10&sort_by=spec_id&sort_dir=asc');
        $page1->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.total', 30);

        $page2 = $this->getJson('/api/rtmf-frontends?page_num=2&limit=10&sort_by=spec_id&sort_dir=asc', [
            'X-Page-Num' => '2',
            'X-Limit' => '10',
        ]);
        $page2->assertOk()
            ->assertJsonPath('meta.page', 2);

        $ids1 = collect($page1->json('data'))->pluck('spec_id')->all();
        $ids2 = collect($page2->json('data'))->pluck('spec_id')->all();

        $this->assertCount(10, $ids1);
        $this->assertCount(10, $ids2);
        $this->assertEmpty(array_intersect($ids1, $ids2));
        $this->assertSame('TST-PG-01', $ids1[0]);
        $this->assertSame('TST-PG-11', $ids2[0]);
    }
}
