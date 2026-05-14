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
        Schema::create('license_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_application_id')
                ->constrained('license_application')
                ->cascadeOnDelete();
            $table->string('document_type')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamp('upload_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_document');
    }
};
