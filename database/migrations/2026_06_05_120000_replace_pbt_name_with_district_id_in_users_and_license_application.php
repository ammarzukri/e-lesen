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
        if (! Schema::hasColumn('users', 'district_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('district_id')->nullable()->after('role')->constrained('districts')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('license_application', 'district_id')) {
            Schema::table('license_application', function (Blueprint $table) {
                $table->foreignId('district_id')->nullable()->after('user_id')->constrained('districts')->nullOnDelete();
            });
        }

        $districtIds = DB::table('districts')->pluck('id', 'district_name');

        if (Schema::hasColumn('users', 'pbt_name')) {
            foreach (DB::table('users')->select('id', 'pbt_name')->whereNotNull('pbt_name')->get() as $user) {
                $districtId = $districtIds[$user->pbt_name] ?? null;

                if ($districtId) {
                    DB::table('users')->where('id', $user->id)->update(['district_id' => $districtId]);
                }
            }
        }

        if (Schema::hasColumn('license_application', 'pbt_name')) {
            foreach (DB::table('license_application')->select('id', 'pbt_name')->whereNotNull('pbt_name')->get() as $application) {
                $districtId = $districtIds[$application->pbt_name] ?? null;

                if ($districtId) {
                    DB::table('license_application')->where('id', $application->id)->update(['district_id' => $districtId]);
                }
            }
        }

        if (Schema::hasColumn('users', 'pbt_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pbt_name');
            });
        }

        if (Schema::hasColumn('license_application', 'pbt_name')) {
            Schema::table('license_application', function (Blueprint $table) {
                $table->dropColumn('pbt_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'pbt_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('pbt_name')->nullable()->after('role');
            });
        }

        if (! Schema::hasColumn('license_application', 'pbt_name')) {
            Schema::table('license_application', function (Blueprint $table) {
                $table->string('pbt_name')->nullable()->after('user_id');
            });
        }

        $districtNames = DB::table('districts')->pluck('district_name', 'id');

        if (Schema::hasColumn('users', 'district_id')) {
            foreach (DB::table('users')->select('id', 'district_id')->whereNotNull('district_id')->get() as $user) {
                $districtName = $districtNames[$user->district_id] ?? null;

                if ($districtName) {
                    DB::table('users')->where('id', $user->id)->update(['pbt_name' => $districtName]);
                }
            }
        }

        if (Schema::hasColumn('license_application', 'district_id')) {
            foreach (DB::table('license_application')->select('id', 'district_id')->whereNotNull('district_id')->get() as $application) {
                $districtName = $districtNames[$application->district_id] ?? null;

                if ($districtName) {
                    DB::table('license_application')->where('id', $application->id)->update(['pbt_name' => $districtName]);
                }
            }
        }

        if (Schema::hasColumn('users', 'district_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('district_id');
            });
        }

        if (Schema::hasColumn('license_application', 'district_id')) {
            Schema::table('license_application', function (Blueprint $table) {
                $table->dropConstrainedForeignId('district_id');
            });
        }
    }
};