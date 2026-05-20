<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminRole  = DB::table('roles')->whereRaw("lower(name) = 'admin'")->first();
        $viewerRole = DB::table('roles')->whereRaw("lower(name) = 'viewer'")->first();

        if ($adminRole) {
            DB::table('users')
                ->whereRaw("lower(role) = 'admin'")
                ->whereNull('role_id')
                ->update(['role_id' => $adminRole->id]);
        }

        if ($viewerRole) {
            DB::table('users')
                ->whereRaw("lower(role) = 'viewer'")
                ->whereNull('role_id')
                ->update(['role_id' => $viewerRole->id]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive — backfilled role_ids are safe to keep
    }
};
