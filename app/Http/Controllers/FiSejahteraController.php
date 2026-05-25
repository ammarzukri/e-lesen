<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\HotelStaff;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\TaxSubmission;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FiSejahteraController extends Controller
{
    protected function monthNumberToCode(int $month): string
    {
        $map = [
            1 => 'jan', 2 => 'feb', 3 => 'mar', 4 => 'apr', 5 => 'may', 6 => 'jun',
            7 => 'jul', 8 => 'aug', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dec',
        ];

        return $map[$month] ?? 'jan';
    }

    protected function monthCodeToNumber(string $month): int
    {
        $map = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];

        return $map[strtolower(trim($month))] ?? 1;
    }

    protected function monthCodeToLabel(string $month): string
    {
        $map = [
            'jan' => 'Januari',
            'feb' => 'Februari',
            'mar' => 'Mac',
            'apr' => 'April',
            'may' => 'Mei',
            'jun' => 'Jun',
            'jul' => 'Julai',
            'aug' => 'Ogos',
            'sep' => 'September',
            'oct' => 'Oktober',
            'nov' => 'November',
            'dec' => 'Disember',
        ];

        return $map[strtolower(trim($month))] ?? strtoupper($month);
    }

    protected function toyyibpayBaseUrl(): string
    {
        return config('services.toyyibpay.sandbox') ? 'https://dev.toyyibpay.com' : 'https://toyyibpay.com';
    }

    protected function calculateTreasurySummary(int $hotelId, int $selectedYear, int $selectedMonth): array
    {
        $monthStart = Carbon::create($selectedYear, $selectedMonth, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $dailyAccumulator = [];
        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $key = $cursor->toDateString();
            $dailyAccumulator[$key] = [
                'date' => $key,
                'date_label' => $cursor->format('d/m/Y'),
                'total_room' => 0,
                'total_amount' => 0.0,
            ];
            $cursor->addDay();
        }

        $bookings = Booking::query()
            ->where('hotel_id', $hotelId)
            ->whereDate('created_at', '<=', $monthEnd->toDateString())
            ->where('total_night', '>', 0)
            ->get(['created_at', 'total_room', 'total_night']);

        foreach ($bookings as $booking) {
            $startDate = Carbon::parse((string) $booking->created_at)->startOfDay();
            $nightCount = max(0, (int) $booking->total_night);
            $roomCount = max(0, (int) $booking->total_room);

            if ($nightCount <= 0 || $roomCount <= 0) {
                continue;
            }

            for ($offset = 0; $offset < $nightCount; $offset++) {
                $affectedDate = $startDate->copy()->addDays($offset);

                if ($affectedDate->lt($monthStart) || $affectedDate->gt($monthEnd)) {
                    continue;
                }

                $key = $affectedDate->toDateString();
                $dailyAccumulator[$key]['total_room'] += $roomCount;
                $dailyAccumulator[$key]['total_amount'] += ($roomCount * 3);
            }
        }

        $dailyBreakdown = collect(array_values($dailyAccumulator));

        return [
            'total_room' => (int) $dailyBreakdown->sum('total_room'),
            'total_amount' => (float) $dailyBreakdown->sum('total_amount'),
            'daily_breakdown' => $dailyBreakdown,
        ];
    }

    protected function generateTaxReceiptDocument(TaxSubmission $submission): string
    {
        $hotelName = $submission->hotel?->name ?? 'Hotel';
        $monthLabel = $this->monthCodeToLabel($submission->month);
        $filePath = 'tax-submissions/receipts/receipt-'.$submission->id.'-'.now()->format('YmdHis').'.pdf';

        $pdf = Pdf::loadView('pdf.fi-sejahtera-tax-receipt', [
            'submission' => $submission,
            'hotelName' => $hotelName,
            'monthLabel' => $monthLabel,
            'generatedAt' => now(),
        ]);

        Storage::disk('public')->put($filePath, $pdf->output());

        return $filePath;
    }

    protected function generateTaxGuestListDocument(TaxSubmission $submission): string
    {
        $monthNumber = $this->monthCodeToNumber($submission->month);
        $monthStart = Carbon::create((int) $submission->year, $monthNumber, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $bookings = Booking::query()
            ->with(['guest:id,name,identity_number,email,phone_number'])
            ->where('hotel_id', $submission->hotel_id)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->orderBy('created_at')
            ->get()
            ->map(function (Booking $booking) {
                return [
                    'date' => optional($booking->created_at)->format('d/m/Y'),
                    'name' => $booking->guest?->name,
                    'identity_number' => $booking->guest?->identity_number,
                    'phone_number' => $booking->guest?->phone_number,
                    'total_room' => (int) $booking->total_room,
                    'total_night' => (int) $booking->total_night,
                    'amount' => (float) $booking->amount,
                ];
            })
            ->values();

        $filePath = 'tax-submissions/guest-reports/guest-list-'.$submission->id.'-'.now()->format('YmdHis').'.pdf';

        $pdf = Pdf::loadView('pdf.fi-sejahtera-payment-guest-list', [
            'submission' => $submission,
            'hotelName' => $submission->hotel?->name ?? 'Hotel',
            'monthLabel' => $this->monthCodeToLabel($submission->month),
            'bookings' => $bookings,
            'generatedAt' => now(),
        ]);

        Storage::disk('public')->put($filePath, $pdf->output());

        return $filePath;
    }

    protected function syncTaxSubmissionPaymentStatus(string $billCode, TaxSubmission $submission): bool
    {
        $baseUrl = $this->toyyibpayBaseUrl();

        $response = Http::asForm()->post($baseUrl.'/index.php/api/getBillTransactions', [
            'billCode' => $billCode,
        ]);

        $payload = $response->json();
        $paymentStatus = data_get($payload, '0.billpaymentStatus')
            ?? data_get($payload, 'billpaymentStatus');

        $isSuccess = in_array((string) $paymentStatus, ['1', 'success', 'SUCCESS'], true);

        if (! $isSuccess) {
            $submission->update([
                'payment_status' => 'Gagal',
                'payment_paid_at' => null,
            ]);

            return false;
        }

        $oldReceipt = $submission->payment_proof;
        $oldGuestList = $submission->guest_report;

        $receiptPath = $this->generateTaxReceiptDocument($submission);
        $guestListPath = $this->generateTaxGuestListDocument($submission);

        if ($oldReceipt && $oldReceipt !== $receiptPath) {
            Storage::disk('public')->delete($oldReceipt);
        }

        if ($oldGuestList && $oldGuestList !== $guestListPath) {
            Storage::disk('public')->delete($oldGuestList);
        }

        $submission->update([
            'payment_status' => 'Berjaya',
            'payment_paid_at' => now(),
            'payment_proof' => $receiptPath,
            'guest_report' => $guestListPath,
            'status' => 'paid',
            'verified_at' => null,
            'verified_by' => null,
            'remarks' => null,
        ]);

        return true;
    }

    protected function resolveGuestHotelsForUser(User $user): array
    {
        $hotels = collect();
        $canSelectHotel = false;
        $allowedHotelIds = collect();
        $selectableHotelIds = collect();

        if ($user->role === 'user') {
            $hotels = Hotel::where('user_id', $user->id)
                ->with('license:id,hotel_id,status,expiry_date')
                ->orderBy('name')
                ->get(['id', 'name']);

            $allowedHotelIds = $hotels->pluck('id');
            $selectableHotelIds = $hotels
                ->filter(fn (Hotel $hotel) => ! $this->isHotelLicenseExpired($hotel))
                ->pluck('id');
            $canSelectHotel = true;
        } elseif ($this->isBktAdmin($user)) {
            $hotels = Hotel::with('license:id,hotel_id,status,expiry_date')
                ->orderBy('name')
                ->get(['id', 'name']);

            $allowedHotelIds = $hotels->pluck('id');
            $selectableHotelIds = $allowedHotelIds;
            $canSelectHotel = true;
        } elseif ($this->isPbtAdmin($user)) {
            $this->ensurePbtAdminHasPbt($user);

            $hotels = Hotel::where('pbt_name', $this->pbtAdminName($user))
                ->with('license:id,hotel_id,status,expiry_date')
                ->orderBy('name')
                ->get(['id', 'name']);

            $allowedHotelIds = $hotels->pluck('id');
            $selectableHotelIds = $allowedHotelIds;
            $canSelectHotel = true;
        } elseif ($user->role === 'staff') {
            $staffHotel = HotelStaff::with('hotel:id,name')
                ->where('user_id', $user->id)
                ->first()?->hotel;

            if ($staffHotel) {
                $hotels = collect([$staffHotel]);
                $allowedHotelIds = collect([$staffHotel->id]);
                $selectableHotelIds = $allowedHotelIds;
            }
        }

        return [
            'hotels' => $hotels,
            'canSelectHotel' => $canSelectHotel,
            'allowedHotelIds' => $allowedHotelIds,
            'selectableHotelIds' => $selectableHotelIds,
        ];
    }

    protected function parseGuestDate(?string $value): ?string
    {
        $dateValue = trim((string) $value);

        if ($dateValue === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $dateValue)->toDateString();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    protected function guestFiltersFromRequest(Request $request, bool $useDefaultRange): array
    {
        $search = trim((string) $request->query('search', ''));
        $selectedHotelId = trim((string) $request->query('hotel_id', ''));

        $defaultStartDate = now()->startOfMonth()->toDateString();
        $defaultEndDate = now()->endOfMonth()->toDateString();

        $startDate = $this->parseGuestDate($request->query('start_date')) ?? '';
        $endDate = $this->parseGuestDate($request->query('end_date')) ?? '';
        $hasDateInput = $request->has('start_date') || $request->has('end_date');

        if ($useDefaultRange && ! $hasDateInput) {
            $startDate = $defaultStartDate;
            $endDate = $defaultEndDate;
        }

        if ($startDate !== '' && $endDate !== '' && Carbon::parse($startDate)->gt(Carbon::parse($endDate))) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [
            'search' => $search,
            'hotel_id' => $selectedHotelId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    protected function buildGuestQuery(array $filters, $allowedHotelIds, $selectableHotelIds): array
    {
        $selectedHotelId = (string) ($filters['hotel_id'] ?? '');
        $search = (string) ($filters['search'] ?? '');
        $startDate = (string) ($filters['start_date'] ?? '');
        $endDate = (string) ($filters['end_date'] ?? '');

        $query = Booking::with([
            'guest:id,name,nationality,identity_number,email,phone_number',
            'hotel:id,name',
        ]);

        if ($allowedHotelIds->isNotEmpty()) {
            $query->whereIn('hotel_id', $allowedHotelIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($selectedHotelId !== '' && ctype_digit($selectedHotelId)) {
            $selectedHotelIdInt = (int) $selectedHotelId;

            if ($selectableHotelIds->contains($selectedHotelIdInt)) {
                $query->where('hotel_id', $selectedHotelIdInt);
            } else {
                $selectedHotelId = '';
            }
        }

        if ($search !== '') {
            $query->whereHas('guest', function ($guestQuery) use ($search) {
                $guestQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('identity_number', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone_number', 'like', '%'.$search.'%');
            });
        }

        if ($startDate !== '') {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate('created_at', '<=', $endDate);
        }

        return [$query, $selectedHotelId];
    }

    protected function enforceFiSejahteraAccess(): ?RedirectResponse
    {
        $this->syncExpiredLicenseStatuses();

        if ($this->hasExpiredStaffHotelLicense()) {
            return redirect()->route('choose.system')->with('error', 'Lesen Penginapan Tamat Tempoh');
        }

        return null;
    }

    protected function syncExpiredLicenseStatuses(): void
    {
        License::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now()->startOfDay())
            ->where('status', '!=', 'Tamat Tempoh')
            ->update([
                'status' => 'Tamat Tempoh',
            ]);
    }

    protected function isHotelLicenseExpired(?Hotel $hotel): bool
    {
        if (! $hotel) {
            return false;
        }

        $license = $hotel->relationLoaded('license') ? $hotel->license : $hotel->license()->first();

        if (! $license) {
            return false;
        }

        if ($license->status === 'Tamat Tempoh') {
            return true;
        }

        return $license->expiry_date && Carbon::parse($license->expiry_date)->startOfDay()->lt(now()->startOfDay());
    }

    protected function hasExpiredStaffHotelLicense(?User $user = null): bool
    {
        $activeUser = $user ?? auth()->user();

        if (($activeUser?->role ?? null) !== 'staff') {
            return false;
        }

        $staffHotel = HotelStaff::with('hotel.license')
            ->where('user_id', $activeUser->id)
            ->first()?->hotel;

        return $this->isHotelLicenseExpired($staffHotel);
    }

    protected function mapHotelOption(Hotel $hotel): array
    {
        $isExpired = $this->isHotelLicenseExpired($hotel);

        return [
            'id' => $hotel->id,
            'name' => $hotel->name,
            'is_expired' => $isExpired,
        ];
    }

    protected function isBktAdmin(?User $user = null): bool
    {
        $role = ($user ?? auth()->user())?->role;

        return in_array($role, ['admin', 'bkt_admin'], true);
    }

    protected function isPbtAdmin(?User $user = null): bool
    {
        return ($user ?? auth()->user())?->role === 'pbt_admin';
    }

    protected function isBendaharaAdmin(?User $user = null): bool
    {
        return ($user ?? auth()->user())?->role === 'bendahara_admin';
    }

    protected function pbtAdminName(?User $user = null): string
    {
        return trim((string) ($user ?? auth()->user())?->pbt_name);
    }

    protected function ensurePbtAdminHasPbt(?User $user = null): void
    {
        if ($this->isPbtAdmin($user) && $this->pbtAdminName($user) === '') {
            abort(403, 'Akaun pbt_admin tidak mempunyai PBT yang ditetapkan.');
        }
    }

    public function paymentCreate()
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $this->ensureOwner();

        $ownedHotelIds = Hotel::query()
            ->where('user_id', auth()->id())
            ->pluck('id');

        $submissions = TaxSubmission::query()
            ->with(['hotel:id,name'])
            ->whereIn('hotel_id', $ownedHotelIds)
            ->latest('id')
            ->get()
            ->map(function (TaxSubmission $submission) {
                return [
                    'id' => $submission->id,
                    'hotel_name' => $submission->hotel?->name,
                    'month' => $submission->month,
                    'year' => $submission->year,
                    'payment_status' => $submission->payment_status,
                    'total_amount' => (float) $submission->payment_amount,
                    'receipt_url' => $submission->payment_proof ? Storage::url($submission->payment_proof) : null,
                    'guest_list_url' => $submission->guest_report ? Storage::url($submission->guest_report) : null,
                    'hotel_guest_list_url' => $submission->hotel_guest_list ? Storage::url($submission->hotel_guest_list) : null,
                    'status' => $submission->status,
                ];
            })
            ->values();

        return Inertia::render('fi sejahtera/PaymentDoc', [
            'submissions' => $submissions,
        ]);
    }

    public function treasuryPayment(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $this->ensureOwner();

        $ownedHotels = Hotel::query()
            ->where('user_id', auth()->id())
            ->with('license:id,hotel_id,status,expiry_date')
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedMonth = (int) $request->query('month', now()->month);
        $selectedYear = (int) $request->query('year', now()->year);
        $selectedHotelId = (string) $request->query('hotel_id', '');

        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = (int) now()->month;
        }

        if ($selectedYear < 2000 || $selectedYear > 2100) {
            $selectedYear = (int) now()->year;
        }

        $selectedHotel = null;

        if ($selectedHotelId !== '' && ctype_digit($selectedHotelId)) {
            $selectedHotel = $ownedHotels->first(
                fn (Hotel $hotel) => $hotel->id === (int) $selectedHotelId
            );
        }

        if (! $selectedHotel) {
            $selectedHotel = $ownedHotels
                ->first(fn (Hotel $hotel) => ! $this->isHotelLicenseExpired($hotel))
                ?? $ownedHotels->first();

            $selectedHotelId = $selectedHotel ? (string) $selectedHotel->id : '';
        }

        $summary = [
            'total_room' => 0,
            'total_amount' => 0.0,
            'daily_breakdown' => collect(),
        ];

        if ($selectedHotelId !== '' && ctype_digit($selectedHotelId)) {
            $summary = $this->calculateTreasurySummary((int) $selectedHotelId, $selectedYear, $selectedMonth);
        }

        return Inertia::render('fi sejahtera/Payment', [
            'ownedHotels' => $ownedHotels->map(fn (Hotel $hotel) => $this->mapHotelOption($hotel))->values(),
            'filters' => [
                'hotel_id' => $selectedHotelId,
                'month' => $selectedMonth,
                'year' => $selectedYear,
            ],
            'summary' => $summary,
        ]);
    }

    public function paymentStart(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $this->ensureOwner();

        $validated = $request->validate([
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $hotel = Hotel::query()
            ->where('user_id', auth()->id())
            ->where('id', (int) $validated['hotel_id'])
            ->with('license:id,hotel_id,status,expiry_date')
            ->first();

        if (! $hotel) {
            throw ValidationException::withMessages([
                'hotel_id' => 'Hotel tidak ditemui untuk akaun ini.',
            ]);
        }

        if ($this->isHotelLicenseExpired($hotel)) {
            throw ValidationException::withMessages([
                'hotel_id' => 'Lesen Penginapan Tamat Tempoh.',
            ]);
        }

        $monthCode = $this->monthNumberToCode((int) $validated['month']);
        $summary = $this->calculateTreasurySummary($hotel->id, (int) $validated['year'], (int) $validated['month']);
        $amount = (float) ($summary['total_amount'] ?? 0.0);

        if ($amount <= 0) {
            return redirect()->route('fi-sejahtera.perbendaharaan', [
                'hotel_id' => $hotel->id,
                'month' => $validated['month'],
                'year' => $validated['year'],
            ])->with('error', 'Tiada jumlah bayaran untuk diteruskan.');
        }

        $submission = TaxSubmission::query()
            ->where('hotel_id', $hotel->id)
            ->where('month', $monthCode)
            ->where('year', (int) $validated['year'])
            ->latest('id')
            ->first();

        if ($submission && in_array($submission->status, ['submitted_to_pbt', 'payment_pending', 'paid', 'submitted', 'bkt_verified', 'verified'], true)) {
            return redirect()->route('fi-sejahtera.payment')->with('success', 'Laporan untuk bulan dan tahun ini telah diwujudkan. Sila teruskan di halaman dokumen pembayaran.');
        }

        if (! $submission) {
            $submission = TaxSubmission::create([
                'hotel_id' => $hotel->id,
                'month' => $monthCode,
                'year' => (int) $validated['year'],
                'payment_proof' => '',
                'guest_report' => '',
                'submitted_at' => now(),
                'status' => 'submitted_to_pbt',
                'payment_status' => 'Belum Dibayar',
                'payment_amount' => $amount,
                'payment_attempted_at' => null,
                'payment_billcode' => null,
                'payment_paid_at' => null,
                'verified_at' => null,
                'verified_by' => null,
                'remarks' => null,
            ]);
        } else {
            $submission->update([
                'payment_amount' => $amount,
                'payment_attempted_at' => null,
                'payment_billcode' => null,
                'payment_paid_at' => null,
                'payment_status' => 'Belum Dibayar',
                'status' => 'submitted_to_pbt',
                'verified_at' => null,
                'verified_by' => null,
                'remarks' => null,
            ]);
        }

        $oldGuestReport = $submission->guest_report;
        $guestReportPath = $this->generateTaxGuestListDocument($submission);

        if ($oldGuestReport && $oldGuestReport !== $guestReportPath) {
            Storage::disk('public')->delete($oldGuestReport);
        }

        $submission->update([
            'guest_report' => $guestReportPath,
            'submitted_at' => now(),
        ]);

        return redirect()->route('fi-sejahtera.payment')->with('success', 'Laporan berjaya dihantar kepada PBT untuk semakan.');
    }

    public function handlePaymentReturn(Request $request)
    {
        $billCode = $request->query('billcode') ?? $request->query('billCode');

        if (! $billCode) {
            return redirect()->route('fi-sejahtera.payment')->with('error', 'Maklumat pembayaran tidak ditemui.');
        }

        $submission = TaxSubmission::query()
            ->with('hotel:id,user_id')
            ->where('payment_billcode', (string) $billCode)
            ->latest('id')
            ->first();

        if (! $submission || $submission->hotel?->user_id !== auth()->id()) {
            return redirect()->route('fi-sejahtera.payment')->with('error', 'Rekod pembayaran tidak sah.');
        }

        $isSuccess = $this->syncTaxSubmissionPaymentStatus((string) $billCode, $submission);

        return redirect()->route('fi-sejahtera.payment')->with(
            $isSuccess ? 'success' : 'error',
            $isSuccess
                ? 'Pembayaran berjaya. Sila klik Hantar untuk serahkan kepada BKT.'
                : 'Pembayaran gagal. Sila cuba semula.'
        );
    }

    public function handlePaymentCallback(Request $request)
    {
        $billCode = $request->input('billcode') ?? $request->input('billCode');

        if (! $billCode) {
            return response()->json(['status' => 'missing-billcode'], 400);
        }

        $submission = TaxSubmission::query()
            ->where('payment_billcode', (string) $billCode)
            ->latest('id')
            ->first();

        if (! $submission) {
            return response()->json(['status' => 'not-found'], 404);
        }

        $this->syncTaxSubmissionPaymentStatus((string) $billCode, $submission);

        return response()->json(['status' => 'ok']);
    }

    public function paymentPay(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $this->ensureOwner();

        $validated = $request->validate([
            'submission_id' => ['required', 'integer', 'exists:tax_submissions,id'],
        ]);

        $submission = TaxSubmission::query()
            ->with('hotel:id,user_id,name')
            ->findOrFail((int) $validated['submission_id']);

        if ($submission->hotel?->user_id !== auth()->id()) {
            abort(403);
        }

        if ($submission->status !== 'payment_pending') {
            return redirect()->route('fi-sejahtera.payment')->with('error', 'Pembayaran hanya boleh dibuat selepas laporan diluluskan oleh PBT.');
        }

        if (! $submission->hotel_guest_list) {
            return redirect()->route('fi-sejahtera.payment')->with('error', 'Sila muat naik Senarai Tetamu (Sistem Hotel) sebelum membuat bayaran.');
        }

        if ($submission->payment_status === 'Berjaya') {
            return redirect()->route('fi-sejahtera.payment')->with('success', 'Bayaran untuk laporan ini telah berjaya direkodkan.');
        }

        $secretKey = config('services.toyyibpay.key') ?? env('TOYYIBPAY_API_KEY');
        $categoryCode = config('services.toyyibpay.category_code') ?? env('TOYYIBPAY_CATEGORY_CODE');

        if (! $secretKey || ! $categoryCode) {
            return redirect()->route('fi-sejahtera.payment')->with('error', 'Tetapan ToyyibPay tidak lengkap. Sila hubungi pentadbir.');
        }

        $baseUrl = $this->toyyibpayBaseUrl();
        $amountInCents = (int) round(((float) $submission->payment_amount) * 100);

        $response = Http::asForm()->post($baseUrl.'/index.php/api/createBill', [
            'userSecretKey' => $secretKey,
            'categoryCode' => $categoryCode,
            'billName' => 'Bayaran Fi Sejahtera',
            'billDescription' => 'Bayaran fi sejahtera untuk '.($submission->hotel?->name ?? 'Hotel'),
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $amountInCents,
            'billReturnUrl' => route('fi-sejahtera.payment.return'),
            'billCallbackUrl' => route('fi-sejahtera.payment.callback'),
            'billExternalReferenceNo' => (string) $submission->id,
            'billTo' => auth()->user()?->name ?? 'Pemilik Hotel',
            'billEmail' => auth()->user()?->email ?? 'no-reply@example.com',
            'billPhone' => auth()->user()?->phone_number ?? '',
        ]);

        if (! $response->successful()) {
            return redirect()->route('fi-sejahtera.payment')->with('error', 'Gagal memulakan pembayaran ToyyibPay. Sila cuba lagi.');
        }

        $payload = $response->json();
        $billCode = data_get($payload, '0.BillCode') ?? data_get($payload, 'BillCode');

        if (! $billCode) {
            return redirect()->route('fi-sejahtera.payment')->with('error', 'Gagal mendapatkan kod pembayaran ToyyibPay.');
        }

        $submission->update([
            'payment_status' => 'Dalam Proses',
            'payment_billcode' => (string) $billCode,
            'payment_attempted_at' => now(),
        ]);

        $paymentUrl = $baseUrl.'/'.$billCode;

        if ($request->header('X-Inertia')) {
            return Inertia::location($paymentUrl);
        }

        return redirect()->away($paymentUrl);
    }

    public function paymentStore(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $this->ensureOwner();

        $validated = $request->validate([
            'submission_id' => ['required', 'integer', 'exists:tax_submissions,id'],
            'hotel_guest_list' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $submission = TaxSubmission::query()
            ->with('hotel:id,user_id')
            ->where('id', (int) $validated['submission_id'])
            ->firstOrFail();

        if ($submission->hotel?->user_id !== auth()->id()) {
            abort(403);
        }

        if ($submission->status !== 'payment_pending') {
            throw ValidationException::withMessages([
                'hotel_guest_list' => 'Muat naik ini hanya dibenarkan selepas PBT meluluskan laporan.',
            ]);
        }

        if ($submission->hotel_guest_list) {
            Storage::disk('public')->delete($submission->hotel_guest_list);
        }

        $hotelGuestListPath = $request->file('hotel_guest_list')->store('tax-submissions/hotel-system-guest-lists', 'public');

        $submission->update([
            'hotel_guest_list' => $hotelGuestListPath,
        ]);

        return redirect()->route('fi-sejahtera.payment')->with('success', 'Senarai tetamu daripada sistem hotel berjaya dimuat naik. Anda kini boleh teruskan pembayaran.');
    }

    public function paymentSubmitToBkt(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $this->ensureOwner();

        $validated = $request->validate([
            'submission_id' => ['required', 'integer', 'exists:tax_submissions,id'],
        ]);

        $submission = TaxSubmission::query()
            ->with('hotel:id,user_id')
            ->findOrFail((int) $validated['submission_id']);

        if ($submission->hotel?->user_id !== auth()->id()) {
            abort(403);
        }

        if ($submission->payment_status !== 'Berjaya') {
            return redirect()->route('fi-sejahtera.payment')->with('error', 'Bayaran belum berjaya. Sila lengkapkan bayaran sebelum hantar kepada BKT.');
        }

        if (! $submission->hotel_guest_list) {
            return redirect()->route('fi-sejahtera.payment')->with('error', 'Sila muat naik senarai tetamu sistem hotel sebelum hantar kepada BKT.');
        }

        $submission->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'verified_at' => null,
            'verified_by' => null,
            'remarks' => null,
        ]);

        return redirect()->route('fi-sejahtera.payment')->with('success', 'Laporan berjaya dihantar kepada BKT untuk semakan.');
    }

    public function dashboard(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $user = auth()->user();

        abort_unless($user, 403);

        $selectedHotelId = (string) $request->query('hotel_id', '');
        $hotels = collect();
        $canSelectHotel = false;
        $allowedHotelIds = collect();
        $selectableHotelIds = collect();

        if ($user->role === 'user') {
            $hotels = Hotel::where('user_id', $user->id)
                ->with('license:id,hotel_id,status,expiry_date')
                ->orderBy('name')
                ->get(['id', 'name']);

            $allowedHotelIds = $hotels->pluck('id');
            $selectableHotelIds = $hotels
                ->filter(fn (Hotel $hotel) => ! $this->isHotelLicenseExpired($hotel))
                ->pluck('id');
            $canSelectHotel = true;
        } elseif ($this->isBktAdmin($user) || $this->isBendaharaAdmin($user)) {
            $hotels = Hotel::with('license:id,hotel_id,status,expiry_date')
                ->orderBy('name')
                ->get(['id', 'name']);
            $allowedHotelIds = $hotels->pluck('id');
            $selectableHotelIds = $allowedHotelIds;
            $canSelectHotel = true;
        } elseif ($this->isPbtAdmin($user)) {
            $this->ensurePbtAdminHasPbt($user);

            $hotels = Hotel::where('pbt_name', $this->pbtAdminName($user))
                ->with('license:id,hotel_id,status,expiry_date')
                ->orderBy('name')
                ->get(['id', 'name']);

            $allowedHotelIds = $hotels->pluck('id');
            $selectableHotelIds = $allowedHotelIds;
            $canSelectHotel = true;
        } elseif ($user->role === 'staff') {
            $staffHotelId = HotelStaff::where('user_id', $user->id)->value('hotel_id');

            if ($staffHotelId) {
                $allowedHotelIds = collect([$staffHotelId]);
                $selectableHotelIds = $allowedHotelIds;
                $hotels = Hotel::with('license:id,hotel_id,status,expiry_date')
                    ->where('id', $staffHotelId)
                    ->get(['id', 'name']);
            }
        }

        $hotelIds = $allowedHotelIds;

        if ($canSelectHotel && $selectedHotelId !== '' && ctype_digit($selectedHotelId)) {
            $selectedHotelIdInt = (int) $selectedHotelId;

            if ($selectableHotelIds->contains($selectedHotelIdInt)) {
                $hotelIds = collect([$selectedHotelIdInt]);
            } else {
                $selectedHotelId = '';
            }
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $baseQuery = Booking::query()
            ->join('guests', 'bookings.guest_id', '=', 'guests.id')
            ->whereBetween('bookings.created_at', [$monthStart, $monthEnd]);

        if ($hotelIds->isNotEmpty()) {
            $baseQuery->whereIn('bookings.hotel_id', $hotelIds);
        } else {
            $baseQuery->whereRaw('1 = 0');
        }

        $totalGuestsThisMonth = (clone $baseQuery)->count();
        $totalTaxPaidThisMonth = (float) (clone $baseQuery)->sum('bookings.amount');

        $countryFrequency = (clone $baseQuery)
            ->select('guests.country as label', DB::raw('count(*) as total'))
            ->whereNotNull('guests.country')
            ->where('guests.country', '!=', '')
            ->groupBy('guests.country')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
            ])
            ->values();

        $stateFrequency = (clone $baseQuery)
            ->select('guests.state as label', DB::raw('count(*) as total'))
            ->whereNotNull('guests.state')
            ->where('guests.state', '!=', '')
            ->groupBy('guests.state')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'total' => (int) $row->total,
            ])
            ->values();

        return Inertia::render('fi sejahtera/Dashboard', [
            'monthlyStats' => [
                'totalGuests' => $totalGuestsThisMonth,
                'totalTaxPaid' => $totalTaxPaidThisMonth,
            ],
            'countryFrequency' => $countryFrequency,
            'stateFrequency' => $stateFrequency,
            'hotels' => $hotels->map(fn (Hotel $hotel) => $this->mapHotelOption($hotel))->values(),
            'canSelectHotel' => $canSelectHotel,
            'filters' => [
                'hotel_id' => $selectedHotelId,
            ],
        ]);
    }

    protected function ensureOwner(): void
    {
        abort_unless(auth()->user()?->role === 'user', 403);
    }

    protected function ensureAdmin(): void
    {
        abort_unless($this->isBktAdmin() || $this->isPbtAdmin(), 403);
    }

    protected function ensureBktAdmin(): void
    {
        abort_unless($this->isBktAdmin(), 403);
    }

    public function staffIndex(Request $request)
    {
        $this->ensureOwner();

        $search = trim((string) $request->query('search', ''));
        $selectedHotelId = (string) $request->query('hotel_id', '');

        $hotels = Hotel::where('user_id', auth()->id())
            ->orderBy('name')
            ->get(['id', 'name']);

        $hotelIds = $hotels->pluck('id');

        $staff = collect();

        if ($hotelIds->isNotEmpty()) {
            $staffQuery = HotelStaff::with('user')
                ->whereIn('hotel_id', $hotelIds);

            if ($selectedHotelId !== '' && ctype_digit($selectedHotelId)) {
                $selectedHotelIdInt = (int) $selectedHotelId;

                if ($hotelIds->contains($selectedHotelIdInt)) {
                    $staffQuery->where('hotel_id', $selectedHotelIdInt);
                } else {
                    $selectedHotelId = '';
                }
            }

            if ($search !== '') {
                $staffQuery->where(function ($query) use ($search) {
                    $query
                        ->where('position', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%')
                                ->orWhere('ic_no', 'like', '%'.$search.'%')
                                ->orWhere('phone_number', 'like', '%'.$search.'%');
                        });
                });
            }

            $staff = $staffQuery
                ->latest('id')
                ->get()
                ->map(function (HotelStaff $hotelStaff) {
                    return [
                        'id' => $hotelStaff->id,
                        'hotel_id' => $hotelStaff->hotel_id,
                        'position' => $hotelStaff->position,
                        'user' => [
                            'id' => $hotelStaff->user?->id,
                            'name' => $hotelStaff->user?->name,
                            'email' => $hotelStaff->user?->email,
                            'ic_no' => $hotelStaff->user?->ic_no,
                            'birth_date' => optional($hotelStaff->user?->birth_date)?->format('Y-m-d'),
                            'birth_place' => $hotelStaff->user?->birth_place,
                            'gender' => $hotelStaff->user?->gender,
                            'citizenship' => $hotelStaff->user?->citizenship,
                            'religion' => $hotelStaff->user?->religion,
                            'ethnicity' => $hotelStaff->user?->ethnicity,
                            'maritial_status' => $hotelStaff->user?->maritial_status,
                            'occupation' => $hotelStaff->user?->occupation,
                            'income' => $hotelStaff->user?->income,
                            'home_address' => $hotelStaff->user?->home_address,
                            'postcode' => $hotelStaff->user?->postcode,
                            'state' => $hotelStaff->user?->state,
                            'district' => $hotelStaff->user?->district,
                            'phone_number' => $hotelStaff->user?->phone_number,
                        ],
                    ];
                })
                ->values();
        }

        return Inertia::render('fi sejahtera/Staff', [
            'staff' => $staff,
            'hasHotel' => $hotelIds->isNotEmpty(),
            'hotels' => $hotels->map(fn (Hotel $hotel) => [
                'id' => $hotel->id,
                'name' => $hotel->name,
            ])->values(),
            'filters' => [
                'search' => $search,
                'hotel_id' => $selectedHotelId,
            ],
        ]);
    }

    public function staffStore(Request $request)
    {
        $this->ensureOwner();

        $ownerHotelIds = Hotel::where('user_id', auth()->id())->pluck('id');

        if ($ownerHotelIds->isEmpty()) {
            throw ValidationException::withMessages([
                'name' => 'Hotel tidak ditemui. Sila lengkapkan proses Fi Sejahtera terlebih dahulu.',
            ]);
        }

        $validated = $request->validate([
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'position' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'ic_no' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'citizenship' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'maritial_status' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'income' => ['nullable', 'string', 'max:30'],
            'home_address' => ['nullable', 'string', 'max:500'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'state' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ]);

        if (! $ownerHotelIds->contains((int) $validated['hotel_id'])) {
            throw ValidationException::withMessages([
                'hotel_id' => 'Hotel yang dipilih tidak sah untuk akaun ini.',
            ]);
        }

        DB::transaction(function () use ($validated) {
            $staffUser = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'staff',
                'ic_no' => $validated['ic_no'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'birth_place' => $validated['birth_place'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'citizenship' => $validated['citizenship'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'ethnicity' => $validated['ethnicity'] ?? null,
                'maritial_status' => $validated['maritial_status'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
                'income' => $validated['income'] ?? null,
                'home_address' => $validated['home_address'] ?? null,
                'postcode' => $validated['postcode'] ?? null,
                'state' => $validated['state'] ?? null,
                'district' => $validated['district'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
            ]);

            HotelStaff::create([
                'user_id' => $staffUser->id,
                'hotel_id' => $validated['hotel_id'],
                'position' => $validated['position'],
            ]);
        });

        return redirect()->route('fi-sejahtera.staff')->with('success', 'Staf berjaya didaftarkan.');
    }

    public function staffUpdate(Request $request, HotelStaff $hotelStaff)
    {
        $this->ensureOwner();

        $ownerHotelIds = Hotel::where('user_id', auth()->id())->pluck('id');

        abort_unless($ownerHotelIds->contains((int) $hotelStaff->hotel_id), 403);

        $validated = $request->validate([
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'position' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$hotelStaff->user_id],
            'password' => ['nullable', 'string', 'min:8'],
            'ic_no' => ['nullable', 'string', 'max:30'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:50'],
            'citizenship' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'maritial_status' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'income' => ['nullable', 'string', 'max:30'],
            'home_address' => ['nullable', 'string', 'max:500'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'state' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
        ]);

        if (! $ownerHotelIds->contains((int) $validated['hotel_id'])) {
            throw ValidationException::withMessages([
                'hotel_id' => 'Hotel yang dipilih tidak sah untuk akaun ini.',
            ]);
        }

        DB::transaction(function () use ($hotelStaff, $validated) {
            $staffUser = $hotelStaff->user()->firstOrFail();

            $staffUser->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => 'staff',
                'ic_no' => $validated['ic_no'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'birth_place' => $validated['birth_place'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'citizenship' => $validated['citizenship'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'ethnicity' => $validated['ethnicity'] ?? null,
                'maritial_status' => $validated['maritial_status'] ?? null,
                'occupation' => $validated['occupation'] ?? null,
                'income' => $validated['income'] ?? null,
                'home_address' => $validated['home_address'] ?? null,
                'postcode' => $validated['postcode'] ?? null,
                'state' => $validated['state'] ?? null,
                'district' => $validated['district'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
            ]);

            if (! empty($validated['password'])) {
                $staffUser->update([
                    'password' => Hash::make($validated['password']),
                ]);
            }

            $hotelStaff->update([
                'hotel_id' => $validated['hotel_id'],
                'position' => $validated['position'],
            ]);
        });

        return redirect()->route('fi-sejahtera.staff')->with('success', 'Staf berjaya dikemas kini.');
    }

    public function staffDestroy(HotelStaff $hotelStaff)
    {
        $this->ensureOwner();

        $ownerHotelIds = Hotel::where('user_id', auth()->id())->pluck('id');
        abort_unless($ownerHotelIds->contains((int) $hotelStaff->hotel_id), 403);

        DB::transaction(function () use ($hotelStaff) {
            $staffUser = $hotelStaff->user;
            $hotelStaff->delete();

            if ($staffUser && $staffUser->role === 'staff') {
                $staffUser->delete();
            }
        });

        return redirect()->route('fi-sejahtera.staff')->with('success', 'Staf berjaya dipadam.');
    }

    public function guestIndex(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $user = auth()->user();

        abort_unless($user, 403);

        $filters = $this->guestFiltersFromRequest($request, true);

        $guestHotelData = $this->resolveGuestHotelsForUser($user);
        $hotels = $guestHotelData['hotels'];
        $canSelectHotel = $guestHotelData['canSelectHotel'];
        $allowedHotelIds = $guestHotelData['allowedHotelIds'];
        $selectableHotelIds = $guestHotelData['selectableHotelIds'];

        [$query, $selectedHotelId] = $this->buildGuestQuery($filters, $allowedHotelIds, $selectableHotelIds);

        $hasHotel = $allowedHotelIds->isNotEmpty();

        $guests = $query->latest('id')
            ->get()
            ->map(function (Booking $booking) {
                return [
                    'id' => $booking->id,
                    'hotel_id' => $booking->hotel_id,
                    'hotel_name' => $booking->hotel?->name,
                    'name' => $booking->guest?->name,
                    'nationality' => $booking->guest?->nationality,
                    'identity_number' => $booking->guest?->identity_number,
                    'email' => $booking->guest?->email,
                    'phone_number' => $booking->guest?->phone_number,
                    'total_room' => $booking->total_room,
                    'total_night' => $booking->total_night,
                    'amount' => $booking->amount,
                    'payment_method' => $booking->payment_method,
                    'created_at' => optional($booking->created_at)->format('Y-m-d H:i'),
                ];
            })
            ->values();

        return Inertia::render('fi sejahtera/Guest', [
            'guests' => $guests,
            'hotels' => $hotels->map(fn (Hotel $hotel) => $this->mapHotelOption($hotel))->values(),
            'hasHotel' => $hasHotel,
            'canSelectHotel' => $canSelectHotel,
            'filters' => [
                'search' => $filters['search'],
                'hotel_id' => $selectedHotelId,
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date'],
            ],
        ]);
    }

    public function guestExportPdf(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $user = auth()->user();

        abort_unless($user, 403);

        $filters = $this->guestFiltersFromRequest($request, false);

        $guestHotelData = $this->resolveGuestHotelsForUser($user);
        $allowedHotelIds = $guestHotelData['allowedHotelIds'];
        $selectableHotelIds = $guestHotelData['selectableHotelIds'];

        [$query] = $this->buildGuestQuery($filters, $allowedHotelIds, $selectableHotelIds);

        $guests = $query->latest('id')
            ->get()
            ->map(function (Booking $booking) {
                return [
                    'name' => $booking->guest?->name,
                    'identity_number' => $booking->guest?->identity_number,
                    'email' => $booking->guest?->email,
                    'phone_number' => $booking->guest?->phone_number,
                    'hotel_name' => $booking->hotel?->name,
                    'total_room' => $booking->total_room,
                    'total_night' => $booking->total_night,
                    'amount' => $booking->amount,
                    'created_at' => optional($booking->created_at)->format('d-m-Y H:i'),
                ];
            })
            ->values();

        $pdf = Pdf::loadView('pdf.fi-sejahtera-guest-report', [
            'guests' => $guests,
            'filters' => [
                'search' => $filters['search'],
                'hotel_id' => $filters['hotel_id'],
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date'],
            ],
            'generatedAt' => now()->format('d-m-Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('fi-sejahtera-guest-report-'.now()->format('Ymd_His').'.pdf');
    }

    public function create()
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $user = auth()->user();
        $initialHotelName = '';
        $isHotelNameFixed = false;
        $ownedHotels = collect();
        $hasHotel = false;

        if ($user?->role === 'staff') {
            $staffHotel = HotelStaff::with('hotel')
                ->where('user_id', $user->id)
                ->first()?->hotel;

            $initialHotelName = $staffHotel?->name ?? '';
            $isHotelNameFixed = true;
            $hasHotel = filled($staffHotel?->id);
        } elseif ($user?->role === 'user') {
            $ownedHotels = Hotel::query()
                ->where('user_id', $user->id)
                ->with('license:id,hotel_id,status,expiry_date')
                ->orderBy('name')
                ->get(['id', 'name']);

            $ownedHotel = $ownedHotels->first();
            $initialHotelName = $ownedHotel?->name ?? '';
            $hasHotel = $ownedHotels->isNotEmpty();
        } elseif ($user) {
            $ownedHotel = Hotel::where('user_id', $user->id)->first();
            $initialHotelName = $ownedHotel?->name ?? '';
            $hasHotel = filled($ownedHotel?->id);
        }

        return Inertia::render('fi sejahtera/Create', [
            'initialHotelName' => $initialHotelName,
            'isHotelNameFixed' => $isHotelNameFixed,
            'hasHotel' => $hasHotel,
            'ownedHotels' => $ownedHotels->map(fn (Hotel $hotel) => $this->mapHotelOption($hotel))->values(),
        ]);
    }

    public function taxList()
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $user = auth()->user();

        abort_unless($user, 403);

        $query = TaxSubmission::query()->with(['hotel:id,name,user_id', 'verifier:id,name']);

        if ($this->isBktAdmin($user)) {
            // admin can review all submissions
        } elseif ($this->isBendaharaAdmin($user)) {
            $query->whereIn('status', ['bkt_verified', 'verified', 'rejected']);
        } elseif ($this->isPbtAdmin($user)) {
            $this->ensurePbtAdminHasPbt($user);

            $query->whereHas('hotel', function ($hotelQuery) use ($user) {
                $hotelQuery->where('pbt_name', $this->pbtAdminName($user));
            });
        } elseif ($user->role === 'user') {
            $ownerHotelIds = Hotel::where('user_id', $user->id)->pluck('id');

            if ($ownerHotelIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('hotel_id', $ownerHotelIds);
            }
        } elseif ($user->role === 'staff') {
            $staffHotelId = HotelStaff::where('user_id', $user->id)->value('hotel_id');

            if ($staffHotelId) {
                $query->where('hotel_id', $staffHotelId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query->whereRaw('1 = 0');
        }

        $submissions = $query
            ->latest('submitted_at')
            ->latest('id')
            ->get()
            ->map(function (TaxSubmission $submission) {
                return [
                    'id' => $submission->id,
                    'hotel_id' => $submission->hotel_id,
                    'hotel_name' => $submission->hotel?->name,
                    'month' => $submission->month,
                    'year' => $submission->year,
                    'amount' => (float) $submission->payment_amount,
                    'payment_proof_url' => $submission->payment_proof ? Storage::url($submission->payment_proof) : null,
                    'guest_report_url' => $submission->guest_report ? Storage::url($submission->guest_report) : null,
                    'hotel_guest_list_url' => $submission->hotel_guest_list ? Storage::url($submission->hotel_guest_list) : null,
                    'submitted_at' => optional($submission->submitted_at)->toDateTimeString(),
                    'status' => $submission->status,
                    'verified_at' => optional($submission->verified_at)->toDateTimeString(),
                    'verified_by_name' => $submission->verifier?->name,
                    'remarks' => $submission->remarks,
                ];
            })
            ->values();

        return Inertia::render('fi sejahtera/TaxList', [
            'submissions' => $submissions,
            'isAdmin' => $this->isBktAdmin($user) || $this->isBendaharaAdmin($user) || $this->isPbtAdmin($user),
            'approverRole' => $this->isBendaharaAdmin($user)
                ? 'bendahara_admin'
                : ($this->isBktAdmin($user)
                    ? 'bkt_admin'
                    : ($this->isPbtAdmin($user) ? 'pbt_admin' : null)),
        ]);
    }

    public function taxVerify(TaxSubmission $taxSubmission)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $user = auth()->user();

        abort_unless($user, 403);

        if ($this->isBendaharaAdmin($user)) {
            if ($taxSubmission->status !== 'bkt_verified') {
                return redirect()->route('fi-sejahtera.tax')->with('error', 'Hanya penghantaran yang telah diluluskan BKT boleh disahkan oleh Bendahara.');
            }

            $taxSubmission->update([
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => auth()->id(),
                'remarks' => null,
            ]);

            return redirect()->route('fi-sejahtera.tax')->with('success', 'Penghantaran cukai berjaya disahkan oleh Bendahara.');
        }

        if ($this->isPbtAdmin($user)) {
            $this->ensurePbtAdminHasPbt($user);
            $submissionPbtName = trim((string) optional($taxSubmission->hotel)->pbt_name);

            if ($submissionPbtName === '' || $submissionPbtName !== $this->pbtAdminName($user)) {
                abort(403);
            }

            if (! in_array($taxSubmission->status, ['submitted_to_pbt', 'rejected'], true)) {
                return redirect()->route('fi-sejahtera.tax')->with('error', 'Hanya laporan yang dihantar kepada PBT boleh diluluskan.');
            }

            $taxSubmission->update([
                'status' => 'payment_pending',
                'verified_at' => now(),
                'verified_by' => auth()->id(),
                'remarks' => null,
            ]);

            return redirect()->route('fi-sejahtera.tax')->with('success', 'Laporan berjaya diluluskan oleh PBT. Pemilik hotel kini boleh membuat bayaran.');
        }

        $this->ensureBktAdmin();

        if (! in_array($taxSubmission->status, ['submitted', 'rejected'], true)) {
            return redirect()->route('fi-sejahtera.tax')->with('error', 'Hanya penghantaran dokumen yang telah dihantar boleh disahkan.');
        }

        $taxSubmission->update([
            'status' => 'bkt_verified',
            'verified_at' => now(),
            'verified_by' => auth()->id(),
            'remarks' => null,
        ]);

        return redirect()->route('fi-sejahtera.tax')->with('success', 'Penghantaran cukai berjaya diluluskan peringkat BKT.');
    }

    public function taxReject(Request $request, TaxSubmission $taxSubmission)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $user = auth()->user();

        abort_unless($user, 403);

        if ($this->isBendaharaAdmin($user)) {
            if ($taxSubmission->status !== 'bkt_verified') {
                return redirect()->route('fi-sejahtera.tax')->with('error', 'Hanya penghantaran yang telah diluluskan BKT boleh ditolak oleh Bendahara.');
            }
        } elseif ($this->isPbtAdmin($user)) {
            $this->ensurePbtAdminHasPbt($user);
            $submissionPbtName = trim((string) optional($taxSubmission->hotel)->pbt_name);

            if ($submissionPbtName === '' || $submissionPbtName !== $this->pbtAdminName($user)) {
                abort(403);
            }

            if (! in_array($taxSubmission->status, ['submitted_to_pbt', 'rejected'], true)) {
                return redirect()->route('fi-sejahtera.tax')->with('error', 'Hanya laporan yang dihantar kepada PBT boleh ditolak.');
            }
        } else {
            $this->ensureBktAdmin();

            if (! in_array($taxSubmission->status, ['submitted', 'rejected'], true)) {
                return redirect()->route('fi-sejahtera.tax')->with('error', 'Hanya penghantaran dokumen yang telah dihantar boleh ditolak.');
            }
        }

        $validated = $request->validate([
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $taxSubmission->update([
            'status' => 'rejected',
            'verified_at' => now(),
            'verified_by' => auth()->id(),
            'remarks' => $validated['remarks'],
        ]);

        return redirect()->route('fi-sejahtera.tax')->with('success', 'Penghantaran cukai telah ditolak.');
    }

    public function store(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $user = auth()->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nationality' => ['required', 'in:Malaysian,Non-Malaysian'],
            'identity_type' => ['required', 'in:IC,Passport'],
            'identity_number' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'total_room' => ['required', 'integer', 'min:1'],
            'total_night' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $nationality = $validated['nationality'];
        $expectedIdentityType = $nationality === 'Malaysian' ? 'IC' : 'Passport';

        if ($validated['identity_type'] !== $expectedIdentityType) {
            throw ValidationException::withMessages([
                'identity_type' => 'Jenis identiti tidak sepadan dengan kewarganegaraan yang dipilih.',
            ]);
        }

        if ($nationality === 'Malaysian' && blank($validated['state'])) {
            throw ValidationException::withMessages([
                'state' => 'Negeri diperlukan untuk warganegara Malaysia.',
            ]);
        }

        if ($nationality !== 'Malaysian') {
            $validated['state'] = null;
        }

        $hotel = null;

        if ($user?->role === 'staff') {
            $hotel = HotelStaff::with('hotel')
                ->where('user_id', $user->id)
                ->first()?->hotel;

            if (! $hotel) {
                throw ValidationException::withMessages([
                    'hotel_name' => 'Hotel staf tidak ditemui. Sila hubungi pentadbir.',
                ]);
            }

            $validated['hotel_name'] = $hotel->name;
        } elseif ($user?->role === 'user') {
            if (blank($validated['hotel_name'])) {
                throw ValidationException::withMessages([
                    'hotel_name' => 'Sila pilih hotel.',
                ]);
            }

            $hotel = Hotel::query()
                ->where('user_id', auth()->id())
                ->where('name', $validated['hotel_name'])
                ->with('license:id,hotel_id,status,expiry_date')
                ->first();

            if (! $hotel) {
                throw ValidationException::withMessages([
                    'hotel_name' => 'Hotel yang dipilih tidak sah untuk pengguna ini.',
                ]);
            }

            if ($this->isHotelLicenseExpired($hotel)) {
                throw ValidationException::withMessages([
                    'hotel_name' => 'Lesen Penginapan Tamat Tempoh.',
                ]);
            }

            $validated['hotel_name'] = $hotel->name;
        } else {
            $application = LicenseApplication::query()
                ->where('user_id', auth()->id())
                ->where('status', 'Diluluskan')
                ->where('payment_status', 'Berjaya')
                ->latest('id')
                ->first();

            if (! $application) {
                throw ValidationException::withMessages([
                    'name' => 'Permohonan lesen perlu berstatus Diluluskan dan pembayaran Berjaya sebelum simpan rekod Fi Sejahtera.',
                ]);
            }

            $hotel = Hotel::updateOrCreate(
                ['license_application_id' => $application->id],
                [
                    'user_id' => auth()->id(),
                    'name' => $validated['hotel_name'] ?: ($application->hotel_name ?: $application->company_name ?: 'Hotel'),
                ],
            );
        }

        DB::transaction(function () use ($validated, $hotel): void {
            $guest = Guest::create([
                'name' => $validated['name'],
                'nationality' => $validated['nationality'],
                'identity_type' => $validated['identity_type'],
                'identity_number' => $validated['identity_number'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'country' => $validated['country'],
                'state' => $validated['state'] ?? null,
            ]);

            Booking::create([
                'hotel_id' => $hotel->id,
                'guest_id' => $guest->id,
                'total_room' => $validated['total_room'],
                'total_night' => $validated['total_night'],
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
            ]);
        });

        return redirect()->route('fi-sejahtera.apply');
    }
}
