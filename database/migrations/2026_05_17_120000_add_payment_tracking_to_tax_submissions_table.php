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
        Schema::table('tax_submissions', function (Blueprint $table) {
            $table->string('payment_status', 30)->default('Belum Dibayar')->after('guest_report');
            $table->decimal('payment_amount', 12, 2)->default(0)->after('payment_status');
            $table->string('payment_billcode', 120)->nullable()->after('payment_amount');
            $table->timestamp('payment_attempted_at')->nullable()->after('payment_billcode');
            $table->timestamp('payment_paid_at')->nullable()->after('payment_attempted_at');
            $table->string('hotel_guest_list')->nullable()->after('guest_report');

            $table->index('payment_billcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_submissions', function (Blueprint $table) {
            $table->dropIndex(['payment_billcode']);
            $table->dropColumn([
                'payment_status',
                'payment_amount',
                'payment_billcode',
                'payment_attempted_at',
                'payment_paid_at',
                'hotel_guest_list',
            ]);
        });
    }
};
