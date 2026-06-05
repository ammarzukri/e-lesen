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
        Schema::create('license_renewal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('license')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('Dalam Proses');
            $table->string('payment_status')->nullable();
            $table->unsignedInteger('payment_amount')->nullable();
            $table->string('payment_billcode')->nullable()->unique();
            $table->timestamp('payment_attempted_at')->nullable();
            $table->timestamp('payment_paid_at')->nullable();
            $table->date('current_expiry_date');
            $table->date('renewed_until_date')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['license_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_renewal');
    }
};
