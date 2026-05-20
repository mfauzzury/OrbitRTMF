<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['admin', 'BA'] as $roleName) {
            $role = DB::table('roles')->whereRaw('lower(name) = lower(?)', [$roleName])->first();
            if (! $role) {
                continue;
            }
            $perms = json_decode($role->permissions, true) ?? [];
            if (! in_array('rtmf.tools', $perms)) {
                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(array_values(array_merge($perms, ['rtmf.tools']))),
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (['admin', 'BA'] as $roleName) {
            $role = DB::table('roles')->whereRaw('lower(name) = lower(?)', [$roleName])->first();
            if (! $role) {
                continue;
            }
            $perms = json_decode($role->permissions, true) ?? [];
            DB::table('roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_filter($perms, fn ($p) => $p !== 'rtmf.tools'))),
                'updated_at'  => now(),
            ]);
        }
    }
};