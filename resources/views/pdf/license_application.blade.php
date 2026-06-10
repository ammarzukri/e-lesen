<!DOCTYPE html>
<html>
<head>
    <title>Borang Permohonan Lesen Rumah Tumpangan</title>

   <script>
     @php
        $gender = [
            'lelaki' => 'Lelaki',
            'perempuan' => 'Perempuan',
        ];

        $company_premises_location = [
            'land' => 'Darat',
            'island' => 'Pulau',
        ];

        $license_type_selected = [
            'homestay_island' => '"Homestay", "Kampungstay", dan "Townstay" di pulau, tasik, atau seumpamanya',
            'homestay_land' => '"Homestay", "Kampungstay", dan "Townstay" selain di pulau, tasik, atau seumpamanya',
            'campsite_island' => 'Tapak perkhemahan dan tapak perkhemahan mewah di pulau, tasik, atau seumpamanya',
            'campsite_land' => 'Tapak perkhemahan dan tapak perkhemahan mewah selain di pulau, tasik, atau seumpamanya',
            'rv_site' => 'Tapak Kenderaan Rekreasi',
            'houseboat_raft_kelong' => 'Rumah bot, rumah rakit, dan kelong',
            'others_island' => 'Mana-mana rumah tumpangan lain di pulau, tasik, atau seumpamanya',
            'others_land' => 'Mana-mana rumah tumpangan lain selain di pulau, tasik, atau seumpamanya',
        ];
    @endphp
    </script>

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo {
            width: 90px;
            height: auto;
            margin-bottom: 10px;
        }

        .title {
            font-size: 18px;
            font-weight: bold;
        }

        .section {
            margin-top: 20px;
        }

        .section-title {
            background-color: #e5e5e5;
            padding: 8px;
            font-weight: bold;
            border: 1px solid #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: top;
        }

        .label {
            width: 35%;
            font-weight: bold;
            background-color: #f7f7f7;
        }

        .indent-big {
            padding-left: 25px;
        }

        .indent {
            padding-left: 17px;
        }

        .signature-container {
            margin-top: 60px;
            text-align: right;
        }

        .signature-box {
            display: inline-block;
            width: 250px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-top: 60px;
            padding-top: 5px;
        }
    </style>
</head>

<body>

    <!-- Header -->
    <div class="header">

        {{-- Example logo path --}}
        <img
            src="{{ public_path('images/jata-negeri.png') }}"
            class="logo"
            alt="Logo"
        >

        <div class="title">
            BORANG PERMOHONAN LESEN RUMAH TUMPANGAN
        </div>

    </div>

    <!-- SECTION 1 -->
    <div class="section">
        <div class="section-title">
            MAKLUMAT PEMOHON
        </div>

        <table>
            <tr>
                <td class="label">1. Nama Pemohon</td>
                <td>{{ $application->user->name ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">2. No. Kad Pengenalan</td>
                <td>{{ $application->user->ic_no ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">3. Tarikh Lahir</td>
                <td>{{ $application->user->birth_date ? \Carbon\Carbon::parse($application->user->birth_date)->format('d/m/Y') : '-' }}</td>
            </tr>

            <tr>
                <td class="label">4. Tempat Lahir</td>
                <td>{{ $application->user->birth_place ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">5. Jantina</td>
                <td>{{ $gender[$application->user->gender] ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">6. Bangsa</td>
                <td>{{ $application->user->ethnicity ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">7. Agama</td>
                <td>{{ $application->user->religion ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">8. Warganegara</td>
                <td>{{ $application->user->citizenship ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">9. Status Perkahwinan</td>
                <td>{{ $application->user->maritial_status ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">10. Pekerjaan</td>
                <td>{{ $application->user->occupation ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">11. Pendapatan</td>
                <td>RM{{ $application->user->income ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">12. Alamat Rumah</td>
                <td>{{ $application->user->home_address ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">13. Poskod</td>
                <td>{{ $application->user->postcode ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent-big">Daerah</td>
                <td>{{ $application->user->district ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent-big">Negeri</td>
                <td>{{ $application->user->state ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">14. No. Telefon</td>
                <td>{{ $application->user->phone_number ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">15. Email</td>
                <td>{{ $application->user->email ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- SECTION 2 -->
    <div class="section">
        <div class="section-title">
            MAKLUMAT PERNIAGAAN/SYARIKAT
        </div>

        <table>
            <tr>
                <td class="label">1. Nama Perniagaan/Syarikat</td>
                <td>{{ $application->company_name ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent">Nama Rumah Tumpangan</td>
                <td>{{ $application->hotel_name ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">2. Alamat Premis</td>
                <td>{{ $application->company_address ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">3. Poskod</td>
                <td>{{ $application->company_postcode ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent">Daerah</td>
                <td>{{ $application->company_district ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent">Negeri</td>
                <td>{{ $application->company_state ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">4. No Telefon Syarikat</td>
                <td>{{ $application->company_phone ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">5. No. Pendaftaran SSM</td>
                <td>{{ $application->company_registration_number ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">6. Tarikh Pendaftaran</td>
                <td>{{ $application->company_registration_date ? \Carbon\Carbon::parse($application->company_registration_date)->format('d/m/Y') : '-' }}</td>
            </tr>

            <tr>
                <td class="label">7. Tarikh Tamat</td>
                <td>{{ $application->company_registration_expiry_date ? \Carbon\Carbon::parse($application->company_registration_expiry_date)->format('d/m/Y') : '-' }}</td>
            </tr>

            <tr>
                <td class="label">Lokasi Premis</td>
                <td>{{ $company_premises_location[$application->company_premises_location] ?? '-' }}</td>
            </tr>            

            <tr>
                <td class="label">8. Pekerja (Melayu)</td>
                <td>{{ $application->employee_malay ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent">Pekerja (Cina)</td>
                <td>{{ $application->employee_chinese ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent">Pekerja (India)</td>
                <td>{{ $application->employee_indian ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent">Pekerja (Lain-lain)</td>
                <td>{{ $application->employee_others ?? '-' }}</td>
            </tr>
            
            <tr>
                <td class="label">9. Waktu Beroperasi</td>
                <td>{{ $application->company_operation_start ?? '-' }} - {{ $application->company_operation_end ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- SECTION 3 -->
    <div class="section">
        <div class="section-title">
            MAKLUMAT PERNIAGAAN/SYARIKAT (IBU PEJABAT)
        </div>

        <table>
            <tr>
                <td class="label">1. Alamat Perniagaan (Ibu Pejabat)</td>
                <td>{{ $application->company_address_hq ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">2. Poskod</td>
                <td>{{ $application->company_postcode_hq ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent">Daerah</td>
                <td>{{ $application->company_district_hq ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label indent">Negeri</td>
                <td>{{ $application->company_state_hq ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">3. No. Telefon</td>
                <td>{{ $application->company_phone_hq ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- SECTION 4 -->
    <div class="section">
        <div class="section-title">
            MAKLUMAT TAMBAHAN
        </div>

        <table>
            <tr>
                <td class="label">1. Kategori Lesen</td>
                <td>{{ $license_type_selected[$application->license_type_selected] ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">2. Bilangan Bilik</td>
                <td>{{ $application->room_count ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <!-- SECTION 5 -->
    <div class="section">
        <div class="section-title">
            AKTIVITI TAMBAHAN
        </div>

        <table>
            <thead>
                <tr>
                    <td style="font-weight: bold; text-align:center;">Bil.</td>
                    <td style="font-weight: bold;">Nama Aktiviti</td>
                    <td style="font-weight: bold;">Jenis Aktiviti</td>
                    <td style="font-weight: bold; text-align:center;">Keluasan (M²)</td>
                    <td style="font-weight: bold; text-align:right;">Amaun (RM)</td>
                </tr>
            </thead>

            <tbody>
                @forelse($application->additionalInfos as $index => $activity)
                    <tr>
                        <td style="text-align:center;">
                            {{ $index + 1 }}
                        </td>

                        <td>
                            {{ $activity->activity_name }}
                        </td>

                        <td>
                            {{ $activity->type_name }}
                        </td>

                        <td style="text-align:center;">
                            {{ $activity->keluasan_mps }}
                        </td>

                        <td style="text-align:right;">
                            {{ number_format($activity->amount, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" style="text-align:center;">
                            Tiada Aktiviti Tambahan
                        </td>
                    </tr>
                @endforelse
                <tr>
                    <td colspan="4" style="text-align:right; font-weight:bold;">
                        Jumlah Keseluruhan
                    </td>

                    <td style="text-align:right; font-weight:bold;">
                        RM {{ number_format($application->additionalInfos->sum('amount'), 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Signature -->
    <div class="signature-container">

        <div class="signature-box">

            <div class="signature-line">
                Tandatangan Pemohon
            </div>

        </div>

    </div>

</body>
</html>