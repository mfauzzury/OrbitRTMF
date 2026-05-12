<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rtmf_frontend_items', function (Blueprint $table) {
            $table->string('validation', 255)->nullable()->after('condition');
            $table->boolean('mandatory')->default(false)->after('validation');
        });
    }

    public function down(): void
    {
        Schema::table('rtmf_frontend_items', function (Blueprint $table) {
            $table->dropColumn(['validation', 'mandatory']);
        });
    }
};
