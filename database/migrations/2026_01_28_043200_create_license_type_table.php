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
        Schema::create('license_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_application_id')
                ->constrained('license_application')
                ->cascadeOnDelete();
            $table->string('aktiviti')->nullable();
            $table->string('keluasan')->nullable();
            $table->string('unit_bilik')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_type');
    }
};
