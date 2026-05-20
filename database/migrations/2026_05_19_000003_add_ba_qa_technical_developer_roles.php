<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $roles = [
            [
                'name'        => 'BA',
                'description' => 'Business Analyst — edits Page Catalog, writes BA feedback, accesses Tools',
                'permissions' => json_encode(['rtmf.view', 'rtmf.catalog', 'rtmf.catalog.edit', 'rtmf.tracker', 'rtmf.feedback']),
            ],
            [
                'name'        => 'QA',
                'description' => 'QA — views Page Catalog, writes QA feedback',
                'permissions' => json_encode(['rtmf.view', 'rtmf.catalog', 'rtmf.tracker', 'rtmf.feedback']),
            ],
            [
                'name'        => 'Technical',
                'description' => 'Technical — views Page Catalog, writes Technical feedback',
                'permissions' => json_encode(['rtmf.view', 'rtmf.catalog', 'rtmf.tracker', 'rtmf.feedback']),
            ],
            [
                'name'        => 'Developer',
                'description' => 'Developer — views Page Catalog, writes Developer feedback',
                'permissions' => json_encode(['rtmf.view', 'rtmf.catalog', 'rtmf.tracker', 'rtmf.feedback']),
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore(array_merge($role, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Update existing Viewer role to include rtmf.tracker
        DB::table('roles')
            ->whereRaw("lower(name) = 'viewer'")
            ->update([
                'permissions' => json_encode(['rtmf.view', 'rtmf.tracker']),
                'updated_at'  => $now,
            ]);

        // Update existing Admin role to include all new permissions
        $adminRole = DB::table('roles')->whereRaw("lower(name) = 'admin'")->first();
        if ($adminRole) {
            $perms    = json_decode($adminRole->permissions, true) ?? [];
            $newPerms = array_values(array_unique(array_merge($perms, [
                'rtmf.catalog', 'rtmf.catalog.edit', 'rtmf.tracker', 'rtmf.feedback',
            ])));
            DB::table('roles')->where('id', $adminRole->id)->update([
                'permissions' => json_encode($newPerms),
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', ['BA', 'QA', 'Technical', 'Developer'])->delete();
    }
};