<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use App\Models\Hotel;
use App\Models\HotelStaff;
use App\Models\License;
use App\Models\LicenseApplication;
use App\Models\LicenseDocument;
use App\Models\LicenseProcessFeePayment;
use App\Models\LicenseRenewal;

class LicenseApplicationController extends Controller
{
    protected int $processFeeAmountInCents = 10000;

    protected function isBktAdmin(): bool
    {
        $role = auth()->user()?->role;

        return in_array($role, ['admin', 'bkt_admin'], true);
    }

    protected function isPbtAdmin(): bool
    {
        return auth()->user()?->role === 'pbt_admin';
    }

    protected function isBendaharaAdmin(): bool
    {
        return auth()->user()?->role === 'bendahara_admin';
    }

    protected function ensureAdmin(): void
    {
        abort_unless($this->isBktAdmin() || $this->isPbtAdmin() || $this->isBendaharaAdmin(), 403);
    }

    protected function ensureBktAdmin(): void
    {
        abort_unless($this->isBktAdmin(), 403);
    }

    protected function ensurePbtAdmin(): void
    {
        abort_unless($this->isPbtAdmin(), 403);
    }

    protected function pbtAdminName(): string
    {
        return trim((string) auth()->user()?->pbt_name);
    }

    protected function ensurePbtAdminHasPbt(): void
    {
        if ($this->isPbtAdmin() && $this->pbtAdminName() === '') {
            abort(403, 'Akaun pbt_admin tidak mempunyai PBT yang ditetapkan.');
        }
    }

    protected function applyPbtScopeToApplications($query)
    {
        if ($this->isPbtAdmin()) {
            $this->ensurePbtAdminHasPbt();
            $query->where('pbt_name', $this->pbtAdminName());
        }

        return $query;
    }

    protected function authorizePbtAdminApplication(LicenseApplication $application): void
    {
        if (! $this->isPbtAdmin()) {
            return;
        }

        $this->ensurePbtAdminHasPbt();
        abort_unless(trim((string) $application->pbt_name) === $this->pbtAdminName(), 403);
    }

    protected function ensureNotStaff(): void
    {
        abort_if(auth()->user()?->role === 'staff', 403, 'Staff hanya boleh melihat status lesen.');
    }

    public function create()
    {
        $this->ensureNotStaff();

        $user = auth()->user();
        $processFeePayment = $this->latestUnconsumedProcessFeePayment(auth()->id());

        if ($processFeePayment && $processFeePayment->payment_status === 'Dalam Proses') {
            $this->syncProcessFeeStatusFromToyyibpay($processFeePayment, false);
            $processFeePayment->refresh();
        }

        $processFeePayload = $this->formatProcessFeePaymentPayload($processFeePayment);

        return Inertia::render('e-lesen/lesen/Create', [
            'initialApplicantInfo' => [
                'name' => $user?->name,
                'ic_no' => $user?->ic_no,
                'birth_date' => $user?->birth_date?->format('Y-m-d'),
                'birth_place' => $user?->birth_place,
                'gender' => $user?->gender,
                'ethnicity' => $user?->ethnicity,
                'religion' => $user?->religion,
                'citizenship' => $user?->citizenship,
                'marital_status' => $user?->maritial_status,
                'occupation' => $user?->occupation,
                'income' => $user?->income,
                'home_address' => $user?->home_address,
                'postcode' => $user?->postcode,
                'state' => $user?->state,
                'district' => $user?->district,
                'phone_number' => $user?->phone_number,
                'email' => $user?->email,
            ],
            'processFeePayment' => [
                'status' => $processFeePayload['status'],
                'paid' => $processFeePayload['paid'],
                'amount' => $processFeePayload['amount'],
                'bill_code' => $processFeePayload['bill_code'],
                'paid_at' => $processFeePayload['paid_at'],
            ],
        ]);
    }

    public function startProcessFeePayment(Request $request)
    {
        $this->ensureNotStaff();

        $existingPaid = LicenseProcessFeePayment::query()
            ->where('user_id', auth()->id())
            ->whereNull('consumed_at')
            ->where('payment_status', 'Berjaya')
            ->latest('id')
            ->first();

        if ($existingPaid) {
            return response()->json([
                'status' => 'paid',
                'paid' => true,
                'amount' => (int) ($existingPaid->payment_amount ?? $this->processFeeAmountInCents),
                'bill_code' => $existingPaid->payment_billcode,
                'paid_at' => optional($existingPaid->payment_paid_at)?->toDateTimeString(),
            ]);
        }

        $baseUrl = $this->toyyibpayBaseUrl();
        $secretKey = config('services.toyyibpay.key') ?? env('TOYYIBPAY_API_KEY');
        $categoryCode = config('services.toyyibpay.category_code') ?? env('TOYYIBPAY_CATEGORY_CODE');

        if (! $secretKey || ! $categoryCode) {
            return response()->json([
                'message' => 'Tetapan pembayaran tidak lengkap. Sila hubungi pentadbir.',
            ], 422);
        }

        $user = auth()->user();
        $externalReference = 'license-process-fee-'.auth()->id().'-'.Str::uuid();
        $response = Http::asForm()->post($baseUrl.'/index.php/api/createBill', [
            'userSecretKey' => $secretKey,
            'categoryCode' => $categoryCode,
            'billName' => 'Bayaran Fi Proses Lesen',
            'billDescription' => 'Bayaran fi proses permohonan lesen',
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $this->processFeeAmountInCents,
            'billReturnUrl' => route('license.process-fee.return'),
            'billCallbackUrl' => route('license.process-fee.callback'),
            'billExternalReferenceNo' => $externalReference,
            'billTo' => $user?->name ?? 'Pemohon',
            'billEmail' => $user?->email ?? 'no-reply@example.com',
            'billPhone' => $user?->phone_number ?? '',
        ]);

        if (! $response->successful()) {
            return response()->json([
                'message' => 'Gagal memulakan pembayaran ToyyibPay. Sila cuba lagi.',
            ], 422);
        }

        $payload = $response->json();
        $billCode = data_get($payload, '0.BillCode') ?? data_get($payload, 'BillCode');

        if (! $billCode) {
            return response()->json([
                'message' => 'Gagal mendapatkan kod pembayaran ToyyibPay.',
            ], 422);
        }

        LicenseProcessFeePayment::create([
            'user_id' => auth()->id(),
            'payment_status' => 'Dalam Proses',
            'payment_amount' => $this->processFeeAmountInCents,
            'payment_billcode' => (string) $billCode,
            'payment_external_reference' => $externalReference,
            'payment_attempted_at' => now(),
        ]);

        return response()->json([
            'status' => 'pending',
            'paid' => false,
            'amount' => $this->processFeeAmountInCents,
            'bill_code' => (string) $billCode,
            'payment_url' => $baseUrl.'/'.(string) $billCode,
        ]);
    }

    public function processFeeStatus(Request $request)
    {
        $this->ensureNotStaff();

        $payment = $this->latestUnconsumedProcessFeePayment(auth()->id());

        if ($payment && $payment->payment_status === 'Dalam Proses') {
            $this->syncProcessFeeStatusFromToyyibpay($payment, false);
            $payment->refresh();
        }

        return response()->json($this->formatProcessFeePaymentPayload($payment));
    }

    public function handleProcessFeeReturn(Request $request)
    {
        $this->ensureNotStaff();

        $billCode = (string) ($request->query('billcode') ?? $request->query('billCode') ?? '');

        if ($billCode !== '') {
            $payment = LicenseProcessFeePayment::query()
                ->where('user_id', auth()->id())
                ->where('payment_billcode', $billCode)
                ->latest('id')
                ->first();

            if ($payment) {
                $this->syncProcessFeeStatusFromToyyibpay($payment, true);
            }
        }

        return redirect()->route('license.apply', [
            'process_fee_return' => 1,
        ]);
    }

    public function handleProcessFeeCallback(Request $request)
    {
        $billCode = (string) ($request->input('billcode') ?? $request->input('billCode') ?? '');

        if ($billCode === '') {
            return response()->json(['status' => 'missing-billcode'], 400);
        }

        $payment = LicenseProcessFeePayment::query()
            ->where('payment_billcode', $billCode)
            ->latest('id')
            ->first();

        if (! $payment) {
            return response()->json(['status' => 'not-found'], 404);
        }

        $this->syncProcessFeeStatusFromToyyibpay($payment, false);

        return response()->json(['status' => 'ok']);
    }

    public function updateApplicantInfo(Request $request)
    {
        $this->ensureNotStaff();

        $validated = $request->validate([
            'applicant_info' => ['required', 'array'],
            'applicant_info.name' => ['nullable', 'string', 'max:255'],
            'applicant_info.ic_no' => ['nullable', 'string', 'max:30'],
            'applicant_info.birth_date' => ['nullable', 'date'],
            'applicant_info.birth_place' => ['nullable', 'string', 'max:255'],
            'applicant_info.gender' => ['nullable', 'string', 'max:50'],
            'applicant_info.ethnicity' => ['nullable', 'string', 'max:100'],
            'applicant_info.religion' => ['nullable', 'string', 'max:100'],
            'applicant_info.citizenship' => ['nullable', 'string', 'max:100'],
            'applicant_info.marital_status' => ['nullable', 'string', 'max:100'],
            'applicant_info.occupation' => ['nullable', 'string', 'max:255'],
            'applicant_info.income' => ['nullable', 'string', 'max:30'],
            'applicant_info.home_address' => ['nullable', 'string', 'max:500'],
            'applicant_info.postcode' => ['nullable', 'string', 'max:20'],
            'applicant_info.state' => ['nullable', 'string', 'max:255'],
            'applicant_info.district' => ['nullable', 'string', 'max:255'],
            'applicant_info.phone_number' => ['nullable', 'string', 'max:30'],
            'applicant_info.email' => ['nullable', 'email', 'max:255'],
        ]);

        $applicant = $validated['applicant_info'];

        auth()->user()?->update([
            'name' => $applicant['name'] ?? null,
            'ic_no' => $applicant['ic_no'] ?? null,
            'birth_date' => $applicant['birth_date'] ?? null,
            'birth_place' => $applicant['birth_place'] ?? null,
            'gender' => $applicant['gender'] ?? null,
            'citizenship' => $applicant['citizenship'] ?? null,
            'religion' => $applicant['religion'] ?? null,
            'ethnicity' => $applicant['ethnicity'] ?? null,
            'maritial_status' => $applicant['marital_status'] ?? null,
            'occupation' => $applicant['occupation'] ?? null,
            'income' => $applicant['income'] ?? null,
            'home_address' => $applicant['home_address'] ?? null,
            'postcode' => $applicant['postcode'] ?? null,
            'state' => $applicant['state'] ?? null,
            'district' => $applicant['district'] ?? null,
            'phone_number' => $applicant['phone_number'] ?? null,
            'email' => $applicant['email'] ?? null,
        ]);

        return response()->noContent();
    }

    public function status()
    {
        $user = auth()->user();

        $applicationsQuery = LicenseApplication::with(['licenseTypes', 'user', 'hotel.license.latestRenewal']);

        if ($user?->role === 'staff') {
            $staffHotelId = HotelStaff::where('user_id', $user->id)->value('hotel_id');

            if ($staffHotelId) {
                $applicationsQuery->whereHas('hotel', function ($query) use ($staffHotelId) {
                    $query->where('id', $staffHotelId);
                });
            } else {
                $applicationsQuery->whereRaw('1 = 0');
            }
        } else {
            $applicationsQuery->where('user_id', auth()->id());
        }

        $applications = $applicationsQuery
            ->latest('id')
            ->get()
            ->map(fn (LicenseApplication $application) => $this->hydrateApplicantFromUser($application));

        return Inertia::render('e-lesen/lesen/Status', [
            'applications' => $applications,
        ]);
    }

    public function startPayment(Request $request)
    {
        $this->ensureNotStaff();

        $applicationQuery = LicenseApplication::where('user_id', auth()->id());

        if ($request->filled('application_id')) {
            $applicationQuery->whereKey($request->integer('application_id'));
        }

        $application = $applicationQuery
            ->with('user')
            ->latest('id')
            ->first();

        if (! $application) {
            return redirect()->route('license.status')->with('payment', [
                'status' => 'error',
                'message' => 'Tiada rekod permohonan ditemui.',
            ]);
        }

        if ($application->status !== 'Diluluskan') {
            return redirect()->route('license.status')->with('payment', [
                'status' => 'error',
                'message' => 'Permohonan belum diluluskan untuk pembayaran.',
            ]);
        }

        if ($application->payment_status === 'Berjaya') {
            return redirect()->route('license.status')->with('payment', [
                'status' => 'success',
                'message' => 'Pembayaran telah berjaya.',
            ]);
        }

        $amountInCents = 10000;
        $baseUrl = $this->toyyibpayBaseUrl();

        $secretKey = config('services.toyyibpay.key') ?? env('TOYYIBPAY_API_KEY');
        $categoryCode = config('services.toyyibpay.category_code') ?? env('TOYYIBPAY_CATEGORY_CODE');

        if (! $secretKey || ! $categoryCode) {
            return redirect()->route('license.status')->with('payment', [
                'status' => 'error',
                'message' => 'Tetapan pembayaran tidak lengkap. Sila hubungi pentadbir.',
            ]);
        }

        $response = Http::asForm()->post($baseUrl.'/index.php/api/createBill', [
            'userSecretKey' => $secretKey,
            'categoryCode' => $categoryCode,
            'billName' => 'Bayaran Lesen',
            'billDescription' => 'Bayaran lesen perniagaan',
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $amountInCents,
            'billReturnUrl' => route('license.payment.return'),
            'billCallbackUrl' => route('license.payment.callback'),
            'billExternalReferenceNo' => (string) $application->id,
            'billTo' => $application->name ?? 'Pemohon',
            'billEmail' => $application->email ?? auth()->user()?->email ?? 'no-reply@example.com',
            'billPhone' => $application->user?->phone_number ?? '',
        ]);

        if (! $response->successful()) {
            return redirect()->route('license.status')->with('payment', [
                'status' => 'error',
                'message' => 'Gagal memulakan pembayaran. Sila cuba lagi.',
            ]);
        }

        $payload = $response->json();
        $billCode = data_get($payload, '0.BillCode') ?? data_get($payload, 'BillCode');

        if (! $billCode) {
            return redirect()->route('license.status')->with('payment', [
                'status' => 'error',
                'message' => 'Gagal memulakan pembayaran. Sila cuba lagi.',
            ]);
        }

        $application->update([
            'payment_status' => 'Dalam Proses',
            'payment_amount' => $amountInCents,
            'payment_billcode' => $billCode,
            'payment_attempted_at' => now(),
        ]);

        $paymentUrl = $baseUrl.'/'.$billCode;

        if ($request->header('X-Inertia')) {
            return Inertia::location($paymentUrl);
        }

        return redirect()->away($paymentUrl);
    }

    public function handlePaymentReturn(Request $request)
    {
        $billCode = $request->query('billcode') ?? $request->query('billCode');

        if (! $billCode) {
            return redirect()->route('license.status')->with('payment', [
                'status' => 'error',
                'message' => 'Maklumat pembayaran tidak ditemui.',
            ]);
        }

        $application = LicenseApplication::where('payment_billcode', $billCode)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();

        if (! $application) {
            return redirect()->route('license.status')->with('payment', [
                'status' => 'error',
                'message' => 'Rekod pembayaran tidak ditemui.',
            ]);
        }

        $paymentSuccess = $this->syncToyyibpayStatus($billCode, $application);

        return redirect()->route('license.status')->with('payment', [
            'status' => $paymentSuccess ? 'success' : 'error',
            'message' => $paymentSuccess
                ? 'Pembayaran berjaya. Terima kasih.'
                : 'Pembayaran gagal. Sila cuba lagi.',
        ]);
    }

    public function handlePaymentCallback(Request $request)
    {
        $billCode = $request->input('billcode') ?? $request->input('billCode');

        if (! $billCode) {
            return response()->json(['status' => 'missing-billcode'], 400);
        }

        $application = LicenseApplication::where('payment_billcode', $billCode)
            ->latest('id')
            ->first();

        if (! $application) {
            return response()->json(['status' => 'not-found'], 404);
        }

        $this->syncToyyibpayStatus($billCode, $application);

        return response()->json(['status' => 'ok']);
    }

    public function startRenewalPayment(Request $request)
    {
        $this->ensureNotStaff();

        $application = LicenseApplication::where('user_id', auth()->id())
            ->whereKey($request->integer('application_id'))
            ->with('user', 'hotel.license.latestRenewal')
            ->first();

        if (! $application) {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Rekod lesen tidak ditemui untuk pembaharuan.',
            ]);
        }

        if ($application->status !== 'Diluluskan' || $application->payment_status !== 'Berjaya') {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Hanya lesen yang telah diluluskan dan dibayar boleh dibaharui.',
            ]);
        }

        $license = $application->hotel?->license;

        if (! $license || ! $license->expiry_date) {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Maklumat lesen tidak lengkap untuk pembaharuan.',
            ]);
        }

        $expiryDate = Carbon::parse($license->expiry_date)->startOfDay();
        $renewalStartDate = $expiryDate->copy()->subMonth();

        if (now()->startOfDay()->lt($renewalStartDate)) {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Lesen hanya boleh dibaharui sebulan sebelum tarikh tamat.',
            ]);
        }

        $pendingRenewal = LicenseRenewal::where('license_id', $license->id)
            ->where('status', 'Dalam Proses')
            ->where('payment_status', 'Berjaya')
            ->latest('id')
            ->first();

        if ($pendingRenewal) {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Permohonan pembaharuan sedang menunggu kelulusan admin.',
            ]);
        }

        $amountInCents = 10000;
        $baseUrl = $this->toyyibpayBaseUrl();
        $secretKey = config('services.toyyibpay.key') ?? env('TOYYIBPAY_API_KEY');
        $categoryCode = config('services.toyyibpay.category_code') ?? env('TOYYIBPAY_CATEGORY_CODE');

        if (! $secretKey || ! $categoryCode) {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Tetapan pembayaran tidak lengkap. Sila hubungi pentadbir.',
            ]);
        }

        $response = Http::asForm()->post($baseUrl.'/index.php/api/createBill', [
            'userSecretKey' => $secretKey,
            'categoryCode' => $categoryCode,
            'billName' => 'Bayaran Pembaharuan Lesen',
            'billDescription' => 'Bayaran pembaharuan lesen perniagaan',
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $amountInCents,
            'billReturnUrl' => route('license.renewal.payment.return'),
            'billCallbackUrl' => route('license.renewal.payment.callback'),
            'billExternalReferenceNo' => 'renewal-'.$license->id,
            'billTo' => $application->name ?? 'Pemohon',
            'billEmail' => $application->email ?? auth()->user()?->email ?? 'no-reply@example.com',
            'billPhone' => $application->user?->phone_number ?? '',
        ]);

        if (! $response->successful()) {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Gagal memulakan pembayaran pembaharuan. Sila cuba lagi.',
            ]);
        }

        $payload = $response->json();
        $billCode = data_get($payload, '0.BillCode') ?? data_get($payload, 'BillCode');

        if (! $billCode) {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Gagal memulakan pembayaran pembaharuan. Sila cuba lagi.',
            ]);
        }

        LicenseRenewal::create([
            'license_id' => $license->id,
            'user_id' => auth()->id(),
            'status' => 'Dalam Proses',
            'payment_status' => 'Dalam Proses',
            'payment_amount' => $amountInCents,
            'payment_billcode' => $billCode,
            'payment_attempted_at' => now(),
            'current_expiry_date' => $expiryDate,
        ]);

        $paymentUrl = $baseUrl.'/'.$billCode;

        if ($request->header('X-Inertia')) {
            return Inertia::location($paymentUrl);
        }

        return redirect()->away($paymentUrl);
    }

    public function handleRenewalPaymentReturn(Request $request)
    {
        $billCode = $request->query('billcode') ?? $request->query('billCode');

        if (! $billCode) {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Maklumat pembayaran pembaharuan tidak ditemui.',
            ]);
        }

        $renewal = LicenseRenewal::where('payment_billcode', $billCode)
            ->where('user_id', auth()->id())
            ->latest('id')
            ->first();

        if (! $renewal) {
            return redirect()->route('license.status')->with('renewal', [
                'status' => 'error',
                'message' => 'Rekod pembaharuan tidak ditemui.',
            ]);
        }

        $paymentSuccess = $this->syncToyyibpayRenewalStatus($billCode, $renewal);

        return redirect()->route('license.status')->with('renewal', [
            'status' => $paymentSuccess ? 'success' : 'error',
            'message' => $paymentSuccess
                ? 'Bayaran pembaharuan berjaya. Menunggu kelulusan admin.'
                : 'Bayaran pembaharuan gagal. Sila cuba lagi.',
        ]);
    }

    public function handleRenewalPaymentCallback(Request $request)
    {
        $billCode = $request->input('billcode') ?? $request->input('billCode');

        if (! $billCode) {
            return response()->json(['status' => 'missing-billcode'], 400);
        }

        $renewal = LicenseRenewal::where('payment_billcode', $billCode)
            ->latest('id')
            ->first();

        if (! $renewal) {
            return response()->json(['status' => 'not-found'], 404);
        }

        $this->syncToyyibpayRenewalStatus($billCode, $renewal);

        return response()->json(['status' => 'ok']);
    }

    protected function syncToyyibpayRenewalStatus(string $billCode, LicenseRenewal $renewal): bool
    {
        $baseUrl = $this->toyyibpayBaseUrl();

        $response = Http::asForm()->post($baseUrl.'/index.php/api/getBillTransactions', [
            'billCode' => $billCode,
        ]);

        $payload = $response->json();
        $paymentStatus = data_get($payload, '0.billpaymentStatus')
            ?? data_get($payload, 'billpaymentStatus');

        $isSuccess = in_array((string) $paymentStatus, ['1', 'success', 'SUCCESS'], true);

        $renewal->update([
            'payment_status' => $isSuccess ? 'Berjaya' : 'Gagal',
            'payment_paid_at' => $isSuccess ? now() : null,
        ]);

        return $isSuccess;
    }

    protected function syncToyyibpayStatus(string $billCode, LicenseApplication $application): bool
    {
        $baseUrl = $this->toyyibpayBaseUrl();

        $response = Http::asForm()->post($baseUrl.'/index.php/api/getBillTransactions', [
            'billCode' => $billCode,
        ]);

        $payload = $response->json();
        $paymentStatus = data_get($payload, '0.billpaymentStatus')
            ?? data_get($payload, 'billpaymentStatus');

        $isSuccess = in_array((string) $paymentStatus, ['1', 'success', 'SUCCESS'], true);

        $application->update([
            'payment_status' => $isSuccess ? 'Berjaya' : 'Gagal',
            'payment_paid_at' => $isSuccess ? now() : null,
        ]);

        if ($isSuccess) {
            $application->refresh();
            $this->syncHotelFromApprovedPaidApplication($application);
        }

        return $isSuccess;
    }

    protected function syncProcessFeeStatusFromToyyibpay(LicenseProcessFeePayment $payment, bool $markFailedWhenNotSuccess = false): bool
    {
        $billCode = (string) $payment->payment_billcode;
        if ($billCode === '') {
            return false;
        }

        $baseUrl = $this->toyyibpayBaseUrl();

        $response = Http::asForm()->post($baseUrl.'/index.php/api/getBillTransactions', [
            'billCode' => $billCode,
        ]);

        $payload = $response->json();
        $paymentStatus = data_get($payload, '0.billpaymentStatus')
            ?? data_get($payload, 'billpaymentStatus');

        $isSuccess = in_array((string) $paymentStatus, ['1', 'success', 'SUCCESS'], true);

        if ($isSuccess) {
            $payment->update([
                'payment_status' => 'Berjaya',
                'payment_paid_at' => now(),
            ]);

            return true;
        }

        if ($markFailedWhenNotSuccess) {
            $payment->update([
                'payment_status' => 'Gagal',
                'payment_paid_at' => null,
            ]);
        }

        return false;
    }

    protected function latestUnconsumedProcessFeePayment(?int $userId): ?LicenseProcessFeePayment
    {
        if (! $userId) {
            return null;
        }

        return LicenseProcessFeePayment::query()
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();
    }

    protected function formatProcessFeePaymentPayload(?LicenseProcessFeePayment $payment): array
    {
        if (! $payment) {
            return [
                'status' => 'unpaid',
                'paid' => false,
                'amount' => $this->processFeeAmountInCents,
                'bill_code' => null,
                'paid_at' => null,
            ];
        }

        $status = match ($payment->payment_status) {
            'Berjaya' => 'paid',
            'Dalam Proses' => 'pending',
            'Gagal' => 'failed',
            default => 'unpaid',
        };

        return [
            'status' => $status,
            'paid' => $payment->payment_status === 'Berjaya',
            'amount' => (int) ($payment->payment_amount ?? $this->processFeeAmountInCents),
            'bill_code' => $payment->payment_billcode,
            'paid_at' => optional($payment->payment_paid_at)?->toDateTimeString(),
        ];
    }

    protected function toyyibpayBaseUrl(): string
    {
        return config('services.toyyibpay.sandbox') ? 'https://dev.toyyibpay.com' : 'https://toyyibpay.com';
    }

    public function adminIndex()
    {
        $this->ensureAdmin();

        $currentMonth = Str::lower(Carbon::now()->format('M'));
        $currentYear = (int) Carbon::now()->format('Y');

        $applicationsQuery = LicenseApplication::with([
            'user',
            'hotel.license',
            'hotel.taxSubmissions' => function ($query) use ($currentMonth, $currentYear) {
                $query
                    ->where('month', $currentMonth)
                    ->where('year', $currentYear)
                    ->where('status', 'verified');
            },
        ]);

        $this->applyPbtScopeToApplications($applicationsQuery);

        $applications = $applicationsQuery
            ->latest('id')
            ->get([
                'id',
                'user_id',
                'name',
                'company_name',
                'hotel_name',
                'pbt_name',
                'status',
                'payment_status',
                'created_at',
            ])
            ->map(function (LicenseApplication $application) {
                $application = $this->hydrateApplicantFromUser($application);

                $isFiSejahteraPaid = (bool) $application->hotel?->taxSubmissions?->isNotEmpty();
                $application->setAttribute('fi_sejahtera_status', $isFiSejahteraPaid ? 'Dibayar' : 'Belum Dibayar');
                $application->setAttribute('license_status', (string) ($application->hotel?->license?->status ?? ''));
                $application->setAttribute('expiry_date', optional($application->hotel?->license?->expiry_date)?->toDateString());

                return $application;
            });

        return Inertia::render('e-lesen/admin/LicenseApplications', [
            'applications' => $applications,
            'permissions' => [
                'canApprove' => $this->isPbtAdmin() || $this->isBktAdmin(),
                'canReject' => $this->isPbtAdmin(),
                'canBlock' => $this->isBktAdmin(),
                'canView' => $this->isPbtAdmin() || $this->isBktAdmin() || $this->isBendaharaAdmin(),
            ],
        ]);
    }

    public function adminShow(LicenseApplication $application)
    {
        $this->ensureAdmin();
        $this->authorizePbtAdminApplication($application);

        $application->load(['user', 'licenseTypes', 'advertisementInfos', 'documents', 'hotel.license']);
        $application = $this->hydrateApplicantFromUser($application);

        return Inertia::render('e-lesen/admin/LicenseApplicationShow', [
            'application' => $application,
        ]);
    }

    public function adminRenewalIndex()
    {
        $this->ensureAdmin();

        $renewalsQuery = LicenseRenewal::with(['license.hotel', 'user']);

        if ($this->isPbtAdmin()) {
            $this->ensurePbtAdminHasPbt();
            $renewalsQuery->whereHas('license.hotel', function ($query) {
                $query->where('pbt_name', $this->pbtAdminName());
            });
        }

        $renewals = $renewalsQuery
            ->latest('id')
            ->get();

        return Inertia::render('e-lesen/admin/LicenseRenewal', [
            'renewals' => $renewals,
            'permissions' => [
                'canApprove' => $this->isPbtAdmin(),
                'canReject' => $this->isPbtAdmin(),
                'canView' => $this->isPbtAdmin() || $this->isBktAdmin() || $this->isBendaharaAdmin(),
            ],
        ]);
    }

    public function adminRenewalShow(LicenseRenewal $renewal)
    {
        $this->ensureAdmin();

        $renewal->load(['license.hotel', 'user']);

        if ($this->isPbtAdmin()) {
            $this->ensurePbtAdminHasPbt();

            abort_unless(
                trim((string) $renewal->license?->hotel?->pbt_name) === $this->pbtAdminName(),
                403
            );
        }

        return Inertia::render('e-lesen/admin/LicenseRenewalShow', [
            'renewal' => $renewal,
        ]);
    }

    public function approveRenewal(LicenseRenewal $renewal)
    {
        $this->ensurePbtAdmin();
        $this->ensurePbtAdminHasPbt();

        $renewal->loadMissing('license.hotel');
        abort_unless(
            trim((string) $renewal->license?->hotel?->pbt_name) === $this->pbtAdminName(),
            403
        );

        if ($renewal->payment_status !== 'Berjaya') {
            return redirect()->back()->with('renewal', [
                'status' => 'error',
                'message' => 'Pembayaran pembaharuan belum berjaya.',
            ]);
        }

        if ($renewal->status === 'Diluluskan') {
            return redirect()->back();
        }

        DB::transaction(function () use ($renewal) {
            $license = License::whereKey($renewal->license_id)->lockForUpdate()->firstOrFail();

            $baseExpiry = Carbon::parse($license->expiry_date)->startOfDay();
            $newExpiry = $baseExpiry->copy()->addYear();

            $license->update([
                'expiry_date' => $newExpiry,
                'status' => 'Aktif',
            ]);

            $renewal->update([
                'status' => 'Diluluskan',
                'approved_at' => now(),
                'rejected_at' => null,
                'renewed_until_date' => $newExpiry,
            ]);
        });

        return redirect()->back();
    }

    public function rejectRenewal(LicenseRenewal $renewal)
    {
        $this->ensurePbtAdmin();
        $this->ensurePbtAdminHasPbt();

        $renewal->loadMissing('license.hotel');
        abort_unless(
            trim((string) $renewal->license?->hotel?->pbt_name) === $this->pbtAdminName(),
            403
        );

        if ($renewal->status === 'Diluluskan') {
            return redirect()->back();
        }

        $renewal->update([
            'status' => 'Ditolak',
            'rejected_at' => now(),
        ]);

        return redirect()->back();
    }

    public function approve(LicenseApplication $application)
    {
        if ($this->isPbtAdmin()) {
            $this->authorizePbtAdminApplication($application);
        } elseif ($this->isBktAdmin()) {
            $application->loadMissing('hotel.license');
            abort_unless($application->hotel?->license?->status === 'Disekat', 403);

            $application->hotel->license->update([
                'status' => 'Aktif',
            ]);

            return redirect()->back();
        } else {
            abort(403);
        }

        $application->update([
            'status' => 'Diluluskan',
        ]);

        $application->refresh();
        $this->syncHotelFromApprovedPaidApplication($application);

        return redirect()->back();
    }

    public function block(LicenseApplication $application)
    {
        $this->ensureBktAdmin();

        $application->loadMissing('hotel.license');
        $license = $application->hotel?->license;

        if (! $license) {
            return redirect()->back()->with('error', 'Lesen tidak ditemui untuk disekat.');
        }

        $license->update([
            'status' => 'Disekat',
        ]);

        return redirect()->back();
    }

    public function reject(LicenseApplication $application)
    {
        $this->ensurePbtAdmin();
        $this->authorizePbtAdminApplication($application);

        $application->update([
            'status' => 'Ditolak',
        ]);

        return redirect()->back();
    }

    public function adminDocument(LicenseDocument $document)
    {
        $this->ensureAdmin();

        if ($this->isPbtAdmin()) {
            $this->ensurePbtAdminHasPbt();

            $application = LicenseApplication::query()
                ->where('id', $document->license_application_id)
                ->where('pbt_name', $this->pbtAdminName())
                ->first();

            abort_unless($application, 403);
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($document->file_path);
    }

    public function store(Request $request)
    {
        $this->ensureNotStaff();

        $request->validate([
            'pbt_name' => ['nullable', 'string'],
            'applicant_info' => ['required', 'array'],
            'company_info' => ['required', 'array'],
            'license_type' => ['nullable', 'array'],
            'advertisement_info' => ['nullable', 'array'],
            'documents' => ['nullable', 'array'],
            'documents.*.document_type' => ['nullable', 'string'],
            'documents.*.file' => ['nullable', 'file', 'max:10240'],
            'processing_fee' => ['required', 'array'],
            'processing_fee.paid' => ['required', 'boolean'],
            'processing_fee.bill_code' => ['nullable', 'string'],
        ]);

        $requestBillCode = (string) $request->input('processing_fee.bill_code', '');
        $requestProcessFeePaid = $request->boolean('processing_fee.paid');

        if ($requestProcessFeePaid !== true || $requestBillCode === '') {
            throw ValidationException::withMessages([
                'processing_fee' => 'Bayaran fi proses belum berjaya. Sila lengkapkan bayaran RM100 terlebih dahulu.',
            ]);
        }

        $processFeePayment = LicenseProcessFeePayment::query()
            ->where('user_id', auth()->id())
            ->where('payment_billcode', $requestBillCode)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $processFeePayment) {
            throw ValidationException::withMessages([
                'processing_fee' => 'Maklumat bayaran fi proses tidak sah. Sila buat bayaran semula.',
            ]);
        }

        if ($processFeePayment->payment_status === 'Dalam Proses') {
            $this->syncProcessFeeStatusFromToyyibpay($processFeePayment, false);
            $processFeePayment->refresh();
        }

        if ($processFeePayment->payment_status !== 'Berjaya') {
            throw ValidationException::withMessages([
                'processing_fee' => 'Bayaran fi proses belum berjaya. Sila lengkapkan bayaran RM100 terlebih dahulu.',
            ]);
        }

        DB::transaction(function () use ($request, $processFeePayment) {
            $lockedProcessFeePayment = LicenseProcessFeePayment::query()
                ->whereKey($processFeePayment->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedProcessFeePayment || $lockedProcessFeePayment->payment_status !== 'Berjaya' || $lockedProcessFeePayment->consumed_at) {
                throw ValidationException::withMessages([
                    'processing_fee' => 'Bayaran fi proses tidak sah atau telah digunakan.',
                ]);
            }

            $applicant = $request->input('applicant_info', []);
            $company = $request->input('company_info', []);

            auth()->user()?->update([
                'ic_no' => $applicant['ic_no'] ?? null,
                'birth_date' => $applicant['birth_date'] ?? null,
                'birth_place' => $applicant['birth_place'] ?? null,
                'gender' => $applicant['gender'] ?? null,
                'citizenship' => $applicant['citizenship'] ?? null,
                'religion' => $applicant['religion'] ?? null,
                'ethnicity' => $applicant['ethnicity'] ?? null,
                'maritial_status' => $applicant['marital_status'] ?? null,
                'occupation' => $applicant['occupation'] ?? null,
                'income' => $applicant['income'] ?? null,
                'home_address' => $applicant['home_address'] ?? null,
                'postcode' => $applicant['postcode'] ?? null,
                'state' => $applicant['state'] ?? null,
                'district' => $applicant['district'] ?? null,
                'phone_number' => $applicant['phone_number'] ?? null,
            ]);

            $licenseApplication = LicenseApplication::create([
                'name' => $applicant['name'] ?? null,
                'user_id' => auth()->id(),
                'pbt_name' => $request->input('pbt_name'),
                'status' => 'Dalam Proses',
                'email' => $applicant['email'] ?? null,
                'company_name' => $company['company_name'] ?? null,
                'hotel_name' => $company['hotel_name'] ?? null,
                'company_address' => $company['company_address'] ?? null,
                'company_postcode' => $company['company_postcode'] ?? null,
                'company_state' => $company['company_state'] ?? null,
                'company_district' => $company['company_district'] ?? null,
                'company_phone' => $company['company_phone'] ?? null,
                'company_registration_number' => $company['company_registration_number'] ?? null,
                'company_registration_date' => $company['company_registration_date'] ?? null,
                'company_registration_expiry_date' => $company['company_registration_expiry_date'] ?? null,
                'company_category' => $company['company_category'] ?? null,
                'company_premises_location' => $company['company_premises_location'] ?? null,
                'employee_malay' => $company['employee_malay'] ?? null,
                'employee_chinese' => $company['employee_chinese'] ?? null,
                'employee_indian' => $company['employee_indian'] ?? null,
                'employee_others' => $company['employee_others'] ?? null,
                'company_operation_start' => $company['company_operation_start'] ?? null,
                'company_operation_end' => $company['company_operation_end'] ?? null,
                'company_address_hq' => $company['company_address_hq'] ?? null,
                'company_postcode_hq' => $company['company_postcode_hq'] ?? null,
                'company_state_hq' => $company['company_state_hq'] ?? null,
                'company_district_hq' => $company['company_district_hq'] ?? null,
                'company_phone_hq' => $company['company_phone_hq'] ?? null,
            ]);

            $licenseTypes = $request->input('license_type', []);
            foreach ($licenseTypes as $row) {
                if (!array_filter($row ?? [])) {
                    continue;
                }
                $licenseApplication->licenseTypes()->create([
                    'aktiviti' => $row['aktiviti'] ?? null,
                    'keluasan' => $row['keluasan'] ?? null,
                    'unit_bilik' => $row['unit_bilik'] ?? null,
                ]);
            }

            $advertisements = $request->input('advertisement_info', []);
            foreach ($advertisements as $row) {
                if (!array_filter($row ?? [])) {
                    continue;
                }
                $licenseApplication->advertisementInfos()->create([
                    'type' => $row['type'] ?? null,
                    'structure' => $row['structure'] ?? null,
                    'length' => $row['length'] ?? null,
                    'width' => $row['width'] ?? null,
                    'number_of_ads' => $row['number_of_ads'] ?? null,
                ]);
            }

            $documents = $request->input('documents', []);
            foreach ($documents as $index => $doc) {
                $file = $request->file("documents.$index.file");
                if (!$file) {
                    continue;
                }

                $path = $file->store('license_documents');

                $licenseApplication->documents()->create([
                    'document_type' => $doc['document_type'] ?? null,
                    'file_path' => $path,
                    'upload_at' => now(),
                ]);
            }

            $lockedProcessFeePayment->update([
                'license_application_id' => $licenseApplication->id,
                'consumed_at' => now(),
            ]);
        });

        return redirect()->route('license.status');
    }

    protected function hydrateApplicantFromUser(LicenseApplication $application): LicenseApplication
    {
        $user = $application->user;

        $application->setAttribute('ic_no', $user?->ic_no);
        $application->setAttribute('birth_date', $user?->birth_date);
        $application->setAttribute('birth_place', $user?->birth_place);
        $application->setAttribute('gender', $user?->gender);
        $application->setAttribute('citizenship', $user?->citizenship);
        $application->setAttribute('religion', $user?->religion);
        $application->setAttribute('ethnicity', $user?->ethnicity);
        $application->setAttribute('maritial_status', $user?->maritial_status);
        $application->setAttribute('occupation', $user?->occupation);
        $application->setAttribute('income', $user?->income);
        $application->setAttribute('home_address', $user?->home_address);
        $application->setAttribute('postcode', $user?->postcode);
        $application->setAttribute('state', $user?->state);
        $application->setAttribute('district', $user?->district);
        $application->setAttribute('phone_number', $user?->phone_number);

        return $application;
    }

    protected function syncHotelFromApprovedPaidApplication(LicenseApplication $application): void
    {
        if ($application->status !== 'Diluluskan' || $application->payment_status !== 'Berjaya') {
            return;
        }

        DB::transaction(function () use ($application) {
            $hotel = Hotel::updateOrCreate(
                ['license_application_id' => $application->id],
                [
                    'user_id' => $application->user_id,
                    'name' => $application->hotel_name ?: 'Hotel',
                    'pbt_name' => $application->pbt_name,
                    'company_name' => $application->company_name,
                    'address' => $application->company_address,
                    'postcode' => $application->company_postcode,
                    'state' => $application->company_state,
                    'district' => $application->company_district,
                    'phone' => $application->company_phone,
                    'registration_number' => $application->company_registration_number,
                    'registration_date' => $application->company_registration_date,
                    'registration_expiry_date' => $application->company_registration_expiry_date,
                    'category' => $application->company_category,
                    'premises_location' => $application->company_premises_location,
                    'employee_malay' => $application->employee_malay,
                    'employee_chinese' => $application->employee_chinese,
                    'employee_indian' => $application->employee_indian,
                    'employee_others' => $application->employee_others,
                    'operation_start' => $application->company_operation_start,
                    'operation_end' => $application->company_operation_end,
                ],
            );

            $this->issueLicenseForHotel($hotel, $application->payment_paid_at);
        });
    }

    protected function issueLicenseForHotel(Hotel $hotel, $startDate = null): void
    {
        if (License::where('hotel_id', $hotel->id)->exists()) {
            return;
        }

        $districtCode = $this->resolveDistrictCode($hotel->pbt_name);
        $locationCode = $this->resolveLocationCode($hotel->premises_location);
        $categoryCode = $this->resolveCategoryCode($hotel->category);
        $licenseStartDate = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();
        $year = $licenseStartDate->format('Y');
        $prefix = sprintf('%s-%s-%s-%s-', $districtCode, $locationCode, $categoryCode, $year);
        $nextIndex = $this->generateNextLicenseIndex($prefix);

        License::create([
            'hotel_id' => $hotel->id,
            'license_number' => sprintf('%s%04d', $prefix, $nextIndex),
            'start_date' => $licenseStartDate,
            'expiry_date' => $licenseStartDate->copy()->addYear(),
            'status' => 'Aktif',
        ]);
    }

    protected function generateNextLicenseIndex(string $prefix): int
    {
        $lastLicense = License::where('license_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('license_number');

        if (! $lastLicense || ! preg_match('/(\d{4})$/', $lastLicense, $matches)) {
            return 1;
        }

        return ((int) $matches[1]) + 1;
    }

    protected function resolveCategoryCode(?string $companyCategory): string
    {
        $normalized = Str::lower(trim((string) $companyCategory));

        if ($normalized === '') {
            return 'T0';
        }

        if (preg_match('/(\d+)/', $normalized, $matches)) {
            return 'T'.$matches[1];
        }

        $wordMapping = [
            'satu' => 'T1',
            'dua' => 'T2',
            'tiga' => 'T3',
            'empat' => 'T4',
            'lima' => 'T5',
            'one' => 'T1',
            'two' => 'T2',
            'three' => 'T3',
            'four' => 'T4',
            'five' => 'T5',
        ];

        foreach ($wordMapping as $keyword => $code) {
            if (Str::contains($normalized, $keyword)) {
                return $code;
            }
        }

        return 'T0';
    }

    protected function resolveDistrictCode(?string $pbtName): string
    {
        $mapping = [
            'Majlis Bandaraya Kuala Terengganu' => 'MBKT',
            'Majlis Perbandaran Kemaman' => 'MPK',
            'Majlis Perbandaran Dungun' => 'MPD',
            'Majlis Daerah Besut' => 'MDB',
            'Majlis Daerah Hulu Terengganu' => 'MDHT',
            'Majlis Daerah Marang' => 'MDM',
            'Majlis Daerah Setiu' => 'MDS',
        ];

        if (isset($mapping[$pbtName ?? ''])) {
            return $mapping[$pbtName];
        }

        return Str::upper(Str::limit(Str::replace([' ', '-'], '', (string) $pbtName), 4, '')) ?: 'PBT';
    }

    protected function resolveLocationCode(?string $premisesLocation): string
    {
        $normalized = Str::lower(trim((string) $premisesLocation));

        if (in_array($normalized, ['i', 'island', 'pulau'], true)) {
            return 'P';
        }

        return 'D';
    }
}
