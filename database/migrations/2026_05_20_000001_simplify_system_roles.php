<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('roles')->insertOrIgnore([
            'name'        => 'User',
            'description' => 'Standard user — access via project membership',
            'permissions' => json_encode([]),
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $userRole = DB::table('roles')->whereRaw("lower(name) = 'user'")->first();

        DB::table('users')
            ->whereRaw("lower(role) != 'admin'")
            ->update([
                'role'       => 'User',
                'role_id'    => $userRole->id,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // Best-effort restore: set all non-admin users back to Viewer
        $viewerRole = DB::table('roles')->whereRaw("lower(name) = 'viewer'")->first();
        if ($viewerRole) {
            DB::table('users')
                ->whereRaw("lower(role) = 'user'")
                ->update([
                    'role'       => 'Viewer',
                    'role_id'    => $viewerRole->id,
                    'updated_at' => now(),
                ]);
        }
    }
};
