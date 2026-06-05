<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\District;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        District::insert([
            [
                'district_name' => 'Majlis Daerah Besut',
                'district_code' => 'MDB',
            ],
            [
                'district_name' => 'Majlis Daerah Setiu',
                'district_code' => 'MDS',
            ],
            [
                'district_name' => 'Majlis Bandaraya Kuala Terengganu',
                'district_code' => 'MBKT',
            ],
            [
                'district_name' => 'Majlis Daerah Hulu Terengganu',
                'district_code' => 'MDHT',
            ],
            [
                'district_name' => 'Majlis Daerah Marang',
                'district_code' => 'MDM',
            ],
            [
                'district_name' => 'Majlis Perbandaran Dungun',
                'district_code' => 'MPD',
            ],
            [
                'district_name' => 'Majlis Perbandaran Kemaman',
                'district_code' => 'MPK',
            ],
        ]);
    }
}