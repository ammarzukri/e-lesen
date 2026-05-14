<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ic_no')->nullable()->after('role');
            $table->date('birth_date')->nullable()->after('ic_no');
            $table->string('birth_place')->nullable()->after('birth_date');
            $table->string('gender')->nullable()->after('birth_place');
            $table->string('citizenship')->nullable()->after('gender');
            $table->string('religion')->nullable()->after('citizenship');
            $table->string('ethnicity')->nullable()->after('religion');
            $table->string('maritial_status')->nullable()->after('ethnicity');
            $table->string('occupation')->nullable()->after('maritial_status');
            $table->string('income')->nullable()->after('occupation');
            $table->string('home_address')->nullable()->after('income');
            $table->string('postcode')->nullable()->after('home_address');
            $table->string('state')->nullable()->after('postcode');
            $table->string('district')->nullable()->after('state');
            $table->string('phone_number')->nullable()->after('district');
        });

        $latestApplications = DB::table('license_application')
            ->select([
                'user_id',
                'ic_no',
                'birth_date',
                'birth_place',
                'gender',
                'citizenship',
                'religion',
                'ethnicity',
                'maritial_status',
                'occupation',
                'income',
                'home_address',
                'postcode',
                'state',
                'district',
                'phone_number',
            ])
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->last());

        foreach ($latestApplications as $userId => $application) {
            DB::table('users')->where('id', $userId)->update([
                'ic_no' => $application->ic_no,
                'birth_date' => $application->birth_date,
                'birth_place' => $application->birth_place,
                'gender' => $application->gender,
                'citizenship' => $application->citizenship,
                'religion' => $application->religion,
                'ethnicity' => $application->ethnicity,
                'maritial_status' => $application->maritial_status,
                'occupation' => $application->occupation,
                'income' => $application->income,
                'home_address' => $application->home_address,
                'postcode' => $application->postcode,
                'state' => $application->state,
                'district' => $application->district,
                'phone_number' => $application->phone_number,
            ]);
        }

        Schema::table('license_application', function (Blueprint $table) {
            $table->dropColumn([
                'ic_no',
                'birth_date',
                'birth_place',
                'gender',
                'citizenship',
                'religion',
                'ethnicity',
                'maritial_status',
                'occupation',
                'income',
                'home_address',
                'postcode',
                'state',
                'district',
                'phone_number',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_application', function (Blueprint $table) {
            $table->string('ic_no')->nullable()->after('pbt_name');
            $table->date('birth_date')->nullable()->after('status');
            $table->string('birth_place')->nullable()->after('birth_date');
            $table->string('gender')->nullable()->after('birth_place');
            $table->string('citizenship')->nullable()->after('gender');
            $table->string('religion')->nullable()->after('citizenship');
            $table->string('ethnicity')->nullable()->after('religion');
            $table->string('maritial_status')->nullable()->after('ethnicity');
            $table->string('occupation')->nullable()->after('maritial_status');
            $table->string('income')->nullable()->after('occupation');
            $table->string('home_address')->nullable()->after('income');
            $table->string('postcode')->nullable()->after('home_address');
            $table->string('state')->nullable()->after('postcode');
            $table->string('district')->nullable()->after('state');
            $table->string('phone_number')->nullable()->after('district');
        });

        DB::table('license_application')
            ->join('users', 'users.id', '=', 'license_application.user_id')
            ->update([
                'license_application.ic_no' => DB::raw('users.ic_no'),
                'license_application.birth_date' => DB::raw('users.birth_date'),
                'license_application.birth_place' => DB::raw('users.birth_place'),
                'license_application.gender' => DB::raw('users.gender'),
                'license_application.citizenship' => DB::raw('users.citizenship'),
                'license_application.religion' => DB::raw('users.religion'),
                'license_application.ethnicity' => DB::raw('users.ethnicity'),
                'license_application.maritial_status' => DB::raw('users.maritial_status'),
                'license_application.occupation' => DB::raw('users.occupation'),
                'license_application.income' => DB::raw('users.income'),
                'license_application.home_address' => DB::raw('users.home_address'),
                'license_application.postcode' => DB::raw('users.postcode'),
                'license_application.state' => DB::raw('users.state'),
                'license_application.district' => DB::raw('users.district'),
                'license_application.phone_number' => DB::raw('users.phone_number'),
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ic_no',
                'birth_date',
                'birth_place',
                'gender',
                'citizenship',
                'religion',
                'ethnicity',
                'maritial_status',
                'occupation',
                'income',
                'home_address',
                'postcode',
                'state',
                'district',
                'phone_number',
            ]);
        });
    }
};
