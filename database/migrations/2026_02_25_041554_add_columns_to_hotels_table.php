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
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('pbt_name')->nullable()->after('license_application_id');
            $table->string('company_name')->nullable()->after('pbt_name');
            $table->string('address')->nullable()->after('name');
            $table->string('postcode')->nullable()->after('address');
            $table->string('state')->nullable()->after('postcode');
            $table->string('district')->nullable()->after('state');
            $table->string('phone')->nullable()->after('district');
            $table->string('registration_number')->nullable()->after('phone');
            $table->date('registration_date')->nullable()->after('registration_number');
            $table->date('registration_expiry_date')->nullable()->after('registration_date');
            $table->unsignedInteger('employee_malay')->nullable()->after('registration_expiry_date');
            $table->unsignedInteger('employee_chinese')->nullable()->after('employee_malay');
            $table->unsignedInteger('employee_indian')->nullable()->after('employee_chinese');
            $table->unsignedInteger('employee_others')->nullable()->after('employee_indian');
            $table->time('operation_start')->nullable()->after('employee_others');
            $table->time('operation_end')->nullable()->after('operation_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'pbt_name',
                'company_name',
                'address',
                'postcode',
                'state',
                'district',
                'phone',
                'registration_number',
                'registration_date',
                'registration_expiry_date',
                'employee_malay',
                'employee_chinese',
                'employee_indian',
                'employee_others',
                'operation_start',
                'operation_end',
            ]);
        });
    }
};
