<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('additional_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_application_id')
                ->constrained('license_application')
                ->cascadeOnDelete();
            $table->string('activity_type')->nullable();
            $table->string('jenis')->nullable();
            $table->string('keluasan_mps')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('additional_info');
    }
};
