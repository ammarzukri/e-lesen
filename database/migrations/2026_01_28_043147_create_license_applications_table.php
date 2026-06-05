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
        Schema::create('license_application', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('ic_no')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('gender')->nullable();
            $table->string('citizenship')->nullable();
            $table->string('religion')->nullable();
            $table->string('ethnicity')->nullable();
            $table->string('maritial_status')->nullable();
            $table->string('occupation')->nullable();
            $table->string('income')->nullable();
            $table->string('home_address')->nullable();
            $table->string('postcode')->nullable();
            $table->string('state')->nullable();
            $table->string('district')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('company_address')->nullable();
            $table->string('company_postcode')->nullable();
            $table->string('company_state')->nullable();
            $table->string('company_district')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_registration_number')->nullable();
            $table->date('company_registration_date')->nullable();
            $table->date('company_registration_expiry_date')->nullable();
            $table->unsignedInteger('employee_malay')->nullable();
            $table->unsignedInteger('employee_chinese')->nullable();
            $table->unsignedInteger('employee_indian')->nullable();
            $table->unsignedInteger('employee_others')->nullable();
            $table->time('company_operation_start')->nullable();
            $table->time('company_operation_end')->nullable();
            $table->string('company_address_hq')->nullable();
            $table->string('company_postcode_hq')->nullable();
            $table->string('company_state_hq')->nullable();
            $table->string('company_district_hq')->nullable();
            $table->string('company_phone_hq')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_application');
    }
};
