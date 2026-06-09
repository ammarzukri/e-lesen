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
        Schema::table('additional_info', function (Blueprint $table) {

            $table->foreignId('additional_activity_id')
                ->nullable()
                ->after('license_application_id')
                ->constrained('additional_activities');

            $table->foreignId('additional_activity_rate_id')
                ->nullable()
                ->after('additional_activity_id')
                ->constrained('additional_activity_rates');

            $table->string('activity_name')->nullable()->after('additional_activity_rate_id');
            $table->string('type_name')->nullable()->after('activity_name');

            $table->decimal('amount', 10, 2)->nullable()->after('type_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('additional_info', function (Blueprint $table) {

            $table->dropForeign(['additional_activity_id']);
            $table->dropForeign(['additional_activity_rate_id']);

            $table->dropColumn([
                'additional_activity_id',
                'additional_activity_rate_id',
                'activity_name',
                'type_name',
                'amount',
            ]);
        });
    }
};
