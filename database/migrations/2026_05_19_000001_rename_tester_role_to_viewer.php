<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'tester')
            ->update([
                'name'        => 'Viewer',
                'description' => 'RTMF viewer — can view and edit within assigned projects',
            ]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'Viewer')
            ->update([
                'name'        => 'tester',
                'description' => null,
            ]);
    }
};
