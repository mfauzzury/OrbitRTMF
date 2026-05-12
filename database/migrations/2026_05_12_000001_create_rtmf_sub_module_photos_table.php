<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rtmf_sub_module_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rtmf_sub_module_id')->constrained('rtmf_sub_modules')->cascadeOnDelete();
            $table->string('filename');
            $table->string('original_name');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');
            $table->string('path');
            $table->string('url');
            $table->timestamps();
            $table->index('rtmf_sub_module_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rtmf_sub_module_photos');
    }
};
