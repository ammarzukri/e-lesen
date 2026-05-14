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
            $table->string('company_category')->nullable()->after('company_registration_expiry_date');
            $table->string('company_premises_location')->nullable()->after('company_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_application', function (Blueprint $table) {
            $table->dropColumn('company_category');
            $table->dropColumn('company_premises_location');
        });
    }
};
