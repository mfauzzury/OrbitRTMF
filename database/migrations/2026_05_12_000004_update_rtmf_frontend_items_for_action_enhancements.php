<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE rtmf_frontend_items ALTER COLUMN condition TYPE TEXT');

        Schema::table('rtmf_frontend_items', function (Blueprint $table) {
            $table->unsignedBigInteger('linked_page_id')->nullable()->after('validation');
            $table->foreign('linked_page_id')
                ->references('id')
                ->on('rtmf_frontends')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rtmf_frontend_items', function (Blueprint $table) {
            $table->dropForeign(['linked_page_id']);
            $table->dropColumn('linked_page_id');
        });

        DB::statement('ALTER TABLE rtmf_frontend_items ALTER COLUMN condition TYPE VARCHAR(255)');
    }
};
