<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the standard unique index that blocks reuse of soft-deleted spec_ids
        DB::statement('ALTER TABLE rtmf_frontends DROP CONSTRAINT IF EXISTS rtmf_frontends_spec_id_unique');

        // Partial unique index — only enforces uniqueness among non-deleted rows
        DB::statement('CREATE UNIQUE INDEX rtmf_frontends_spec_id_active_unique ON rtmf_frontends (spec_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS rtmf_frontends_spec_id_active_unique');
        DB::statement('ALTER TABLE rtmf_frontends ADD CONSTRAINT rtmf_frontends_spec_id_unique UNIQUE (spec_id)');
    }
};
