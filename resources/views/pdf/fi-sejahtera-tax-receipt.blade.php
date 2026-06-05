<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <title>Resit Pembayaran Fi Sejahtera</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; }
        .title { font-size: 18px; font-weight: 700; margin-bottom: 12px; }
        .meta { margin-bottom: 6px; }
        .box { margin-top: 16px; border: 1px solid #d1d5db; padding: 12px; }
        .amount { font-size: 20px; font-weight: 700; margin-top: 8px; }
    </style>
</head>
<body>
    <div class="title">Resit Pembayaran Fi Sejahtera</div>

    <div class="meta"><strong>Hotel:</strong> {{ $hotelName }}</div>
    <div class="meta"><strong>Bulan/Tahun:</strong> {{ $monthLabel }} {{ $submission->year }}</div>
    <div class="meta"><strong>Bill Code:</strong> {{ $submission->payment_billcode ?? '-' }}</div>
    <div class="meta"><strong>Tarikh Bayaran:</strong> {{ optional($submission->payment_paid_at)->format('d/m/Y H:i') ?? '-' }}</div>

    <div class="box">
        <div><strong>Jumlah Bayaran</strong></div>
        <div class="amount">RM {{ number_format((float) $submission->payment_amount, 2) }}</div>
    </div>

    <div style="margin-top: 18px; color: #6b7280; font-size: 11px;">
        Dijana pada {{ $generatedAt->format('d/m/Y H:i') }}
    </div>
</body>
</html>
