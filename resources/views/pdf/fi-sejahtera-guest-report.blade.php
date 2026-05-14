<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Laporan Tetamu Fi Sejahtera</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111827;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .meta {
            margin-bottom: 10px;
        }

        .meta p {
            margin: 2px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 6px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: left;
        }

        .empty {
            text-align: center;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <h1>Laporan Senarai Tetamu</h1>

    <div class="meta">
        <p><strong>Tarikh Jana:</strong> {{ $generatedAt }}</p>
        <p><strong>Carian:</strong> {{ $filters['search'] !== '' ? $filters['search'] : 'Semua' }}</p>
        <p><strong>Tarikh Mula:</strong> {{ $filters['start_date'] ?: 'Semua' }}</p>
        <p><strong>Tarikh Tamat:</strong> {{ $filters['end_date'] ?: 'Semua' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Bil.</th>
                <th>Nama</th>
                <th>No Identiti (IC/Passport)</th>
                <th>Emel</th>
                <th>No Telefon</th>
                <th>Hotel</th>
                <th>Kuantiti Bilik</th>
                <th>Jumlah Malam</th>
                <th>Jumlah (RM)</th>
                <th>Tarikh</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($guests as $index => $guest)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $guest['name'] ?: '-' }}</td>
                    <td>{{ $guest['identity_number'] ?: '-' }}</td>
                    <td>{{ $guest['email'] ?: '-' }}</td>
                    <td>{{ $guest['phone_number'] ?: '-' }}</td>
                    <td>{{ $guest['hotel_name'] ?: '-' }}</td>
                    <td>{{ $guest['total_room'] ?? '-' }}</td>
                    <td>{{ $guest['total_night'] ?? '-' }}</td>
                    <td>{{ $guest['amount'] ?? '-' }}</td>
                    <td>{{ $guest['created_at'] ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="empty">Tiada rekod tetamu dijumpai.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
