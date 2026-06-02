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
            $table->string('license_type_selected')->nullable()->after('company_premises_location');
            $table->string('room_count')->nullable()->after('license_type_selected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_application', function (Blueprint $table) {
            $table->dropColumn(['license_type_selected', 'room_count']);
        });
    }
};
