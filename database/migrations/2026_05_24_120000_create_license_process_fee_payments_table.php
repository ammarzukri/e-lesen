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
        Schema::create('license_process_fee_payment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('license_application_id')->nullable()->constrained('license_application')->nullOnDelete();
            $table->string('payment_status')->default('Belum Dibayar');
            $table->unsignedInteger('payment_amount')->nullable();
            $table->string('payment_billcode')->nullable()->unique();
            $table->string('payment_external_reference')->nullable();
            $table->timestamp('payment_attempted_at')->nullable();
            $table->timestamp('payment_paid_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'payment_status']);
            $table->index(['user_id', 'consumed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_process_fee_payment');
    }
};
