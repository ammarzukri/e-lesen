<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Guest List Fi Sejahtera</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; }
        .title { font-size: 16px; font-weight: 700; margin-bottom: 10px; }
        .meta { margin-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #9ca3af; padding: 6px; text-align: left; }
        th { background: #f3f4f6; }
    </style>
</head>
<body>
    <div class="title">Senarai Tetamu Bulanan Fi Sejahtera</div>

    <div class="meta"><strong>Hotel:</strong> {{ $hotelName }}</div>
    <div class="meta"><strong>Bulan/Tahun:</strong> {{ $monthLabel }} {{ $submission->year }}</div>

    <table>
        <thead>
            <tr>
                <th>Tarikh</th>
                <th>Nama Tetamu</th>
                <th>No. Identiti</th>
                <th>No. Telefon</th>
                <th>Jumlah Bilik</th>
                <th>Jumlah Malam</th>
                <th>Jumlah (RM)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $booking)
                <tr>
                    <td>{{ $booking['date'] ?? '-' }}</td>
                    <td>{{ $booking['name'] ?? '-' }}</td>
                    <td>{{ $booking['identity_number'] ?? '-' }}</td>
                    <td>{{ $booking['phone_number'] ?? '-' }}</td>
                    <td>{{ $booking['total_room'] ?? 0 }}</td>
                    <td>{{ $booking['total_night'] ?? 0 }}</td>
                    <td>{{ number_format((float) ($booking['amount'] ?? 0), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Tiada rekod tetamu dalam tempoh ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 14px; color: #6b7280; font-size: 10px;">
        Dijana pada {{ $generatedAt->format('d/m/Y H:i') }}
    </div>
</body>
</html>
