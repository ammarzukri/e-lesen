<!DOCTYPE html>
<html>
<head>
    <title>Borang Permohonan Lesen Rumah Tumpangan</title>

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
                <td class="label">Nama Pemohon</td>
                <td>{{ $application->user->name ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">No. Kad Pengenalan</td>
                <td>{{ $application->user->ic_no ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Tarikh Lahir</td>
                <td>{{ $application->user->birth_date ? \Carbon\Carbon::parse($application->user->birth_date)->format('d/m/Y') : '-' }}</td>
            </tr>

            <tr>
                <td class="label">Tempat Lahir</td>
                <td>{{ $application->user->birth_place ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Jantina</td>
                <td>{{ $application->user->gender ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Bangsa</td>
                <td>{{ $application->user->ethnicity ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Agama</td>
                <td>{{ $application->user->religion ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Warganegara</td>
                <td>{{ $application->user->citizenship ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Status Perkahwinan</td>
                <td>{{ $application->user->maritial_status ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Pekerjaan</td>
                <td>{{ $application->user->occupation ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Pendapatan</td>
                <td>RM{{ $application->user->income ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Alamat Rumah</td>
                <td>{{ $application->user->home_address ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Poskod</td>
                <td>{{ $application->user->postcode ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Daerah</td>
                <td>{{ $application->user->district ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Negeri</td>
                <td>{{ $application->user->state ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">No. Telefon</td>
                <td>{{ $application->user->phone_number ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Email</td>
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
                <td class="label">Nama Perniagaan/Syarikat</td>
                <td>{{ $application->company_name ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Nama Rumah Tumpangan</td>
                <td>{{ $application->hotel_name ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">No. Pendaftaran SSM</td>
                <td>{{ $application->company_registration_number ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Tarikh Pendaftaran</td>
                <td>{{ $application->company_registration_date ? \Carbon\Carbon::parse($application->company_registration_date)->format('d/m/Y') : '-' }}</td>
            </tr>

            <tr>
                <td class="label">Tarikh Tamat</td>
                <td>{{ $application->company_registration_expiry_date ? \Carbon\Carbon::parse($application->company_registration_expiry_date)->format('d/m/Y') : '-' }}</td>
            </tr>

            <tr>
                <td class="label">Lokasi Premis</td>
                <td>{{ $application->company_premises_location ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Alamat Premis</td>
                <td>{{ $application->company_address ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Poskod</td>
                <td>{{ $application->company_postcode ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Daerah</td>
                <td>{{ $application->company_district ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Negeri</td>
                <td>{{ $application->company_state ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">No Telefon Syarikat</td>
                <td>{{ $application->company_phone ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Waktu Beroperasi</td>
                <td>{{ $application->company_operation_start ?? '-' }} - {{ $application->company_operation_end ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Pekerja (Melayu)</td>
                <td>{{ $application->employee_malay ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Pekerja (Cina)</td>
                <td>{{ $application->employee_chinese ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Pekerja (India)</td>
                <td>{{ $application->employee_indian ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Pekerja (Lain-lain)</td>
                <td>{{ $application->employee_others ?? '-' }}</td>
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
                <td class="label">Alamat Perniagaan (Ibu Pejabat)</td>
                <td>{{ $application->company_address_hq ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Poskod</td>
                <td>{{ $application->company_postcode_hq ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Daerah</td>
                <td>{{ $application->company_district_hq ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Negeri</td>
                <td>{{ $application->company_state_hq ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">No. Telefon</td>
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
                <td class="label">Kategori Lesen</td>
                <td>{{ $application->license_type_selected ?? '-' }}</td>
            </tr>

            <tr>
                <td class="label">Bilangan Bilik</td>
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