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
        Schema::table('license_application', function (Blueprint $table) {
            $table->string('payment_status')->nullable()->default('Belum Dibayar');
            $table->unsignedInteger('payment_amount')->nullable();
            $table->string('payment_billcode')->nullable();
            $table->timestamp('payment_attempted_at')->nullable();
            $table->timestamp('payment_paid_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_application', function (Blueprint $table) {
            $table->dropColumn([
                'payment_status',
                'payment_amount',
                'payment_billcode',
                'payment_attempted_at',
                'payment_paid_at',
            ]);
        });
    }
};
