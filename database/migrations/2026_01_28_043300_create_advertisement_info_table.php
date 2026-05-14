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
        Schema::create('advertisement_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_application_id')
                ->constrained('license_application')
                ->cascadeOnDelete();
            $table->string('type')->nullable();
            $table->string('structure')->nullable();
            $table->string('length')->nullable();
            $table->string('width')->nullable();
            $table->string('number_of_ads')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertisement_info');
    }
};
