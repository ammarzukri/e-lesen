<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Carbon\Carbon;
use App\Http\Controllers\LicenseApplicationController;
use App\Http\Controllers\FiSejahteraController;
use App\Http\Controllers\CountryController;
use App\Models\District;
use App\Models\HotelStaff;
use App\Models\License;
use App\Models\LicenseApplication;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
    ]);
})->name('home');

// Route for Country API
Route::get('/countries', [CountryController::class, 'index'])
    ->middleware(['auth'])
    ->name('countries.index');

Route::get('dashboard', function () {
    $user = auth()->user();
    $isBktAdmin = $user && in_array($user->role, ['admin', 'bkt_admin', 'bendahara_admin', 'super_admin'], true);
    $isPbtAdmin = $user && $user->role === 'pbt_admin';

    if ($isBktAdmin || $isPbtAdmin) {
        $applicationsQuery = LicenseApplication::query()->with('pbtDistrict');

        if ($isPbtAdmin) {
            $pbtDistrictId = $user?->district_id;

            if (! $pbtDistrictId) {
                abort(403, 'Akaun pbt_admin tidak mempunyai PBT yang ditetapkan.');
            }

            $applicationsQuery->where('district_id', $pbtDistrictId);
        }

        $totalApplications = (clone $applicationsQuery)->count();
        $approvedApplications = (clone $applicationsQuery)->where('status', 'Diluluskan')->count();
        $rejectedApplications = (clone $applicationsQuery)->where('status', 'Ditolak')->count();
        $blockedApplications = (clone $applicationsQuery)->where('status', 'Disekat')->count();

        // only count approved applications per PBT for the pie chart
        $districtNames = District::query()->pluck('district_name', 'id');
        $byPbt = (clone $applicationsQuery)
            ->where('status', 'Diluluskan')
            ->select('district_id', DB::raw('count(*) as total'))
            ->groupBy('district_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($districtNames) {
                return [
                    'name' => $districtNames[$row->district_id] ?? 'Tidak Dinyatakan',
                    'total' => (int) $row->total,
                ];
            })
            ->values();

        return Inertia::render('e-lesen/admin/AdminDashboard', [
            'adminStats' => [
                'totals' => [
                    'total' => $totalApplications,
                    'approved' => $approvedApplications,
                    'rejected' => $rejectedApplications,
                    'blocked' => $blockedApplications,
                ],
                'byPbt' => $byPbt,
            ],
        ]);
    }

    if ($user && $user->role === 'staff') {
        $staffHotelId = HotelStaff::where('user_id', $user->id)->value('hotel_id');

        $staffApplications = LicenseApplication::query()->with(['licenseTypes', 'pbtDistrict', 'hotel.license']);

        if ($staffHotelId) {
            $staffApplications->whereHas('hotel', function ($query) use ($staffHotelId) {
                $query->where('id', $staffHotelId);
            });
        } else {
            $staffApplications->whereRaw('1 = 0');
        }

        $totalApplications = (clone $staffApplications)->count();
        $approvedApplications = (clone $staffApplications)->where('status', 'Diluluskan')->count();
        $rejectedApplications = (clone $staffApplications)->where('status', 'Ditolak')->count();
        $pendingApplications = max(0, $totalApplications - $approvedApplications - $rejectedApplications);

        $recentApplications = (clone $staffApplications)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'created_at' => optional($a->created_at)->toDateTimeString(),
                    'hotel_name' => $a->hotel_name,
                    'company_name' => $a->company_name,
                    'status' => $a->status,
                    'payment_status' => $a->payment_status,
                    'pbt_name' => $a->pbtDistrict?->district_name,
                    'start_date' => optional($a->hotel?->license?->start_date)->toDateString(),
                    'expiry_date' => optional($a->hotel?->license?->expiry_date)->toDateString(),
                    'license_status' => $a->hotel?->license?->status,
                    'license_number' => $a->hotel?->license?->license_number,
                    'types' => $a->license_type_selected
                        ? collect([$a->license_type_selected])
                        : $a->licenseTypes->map(function ($t) {
                            return $t->aktiviti;
                        })->values(),
                ];
            })->values();

        return Inertia::render('e-lesen/Dashboard', [
            'dashboardStats' => [
                'total' => $totalApplications,
                'approved' => $approvedApplications,
                'rejected' => $rejectedApplications,
                'pending' => $pendingApplications,
            ],
            'recentApplications' => $recentApplications,
        ]);
    }

    $userApplications = LicenseApplication::query()->where('user_id', $user->id);

    $totalApplications = (int) $userApplications->count();
    $approvedApplications = (int) LicenseApplication::where('user_id', $user->id)
        ->where('status', 'Diluluskan')
        ->count();
    $rejectedApplications = (int) LicenseApplication::where('user_id', $user->id)
        ->where('status', 'Ditolak')
        ->count();

    $pendingApplications = max(0, $totalApplications - $approvedApplications - $rejectedApplications);

    $recentApplications = LicenseApplication::with(['licenseTypes', 'pbtDistrict', 'hotel.license'])
        ->where('user_id', $user->id)
        ->latest()
        ->limit(5)
        ->get()
        ->map(function ($a) {
            return [
                'id' => $a->id,
                'created_at' => optional($a->created_at)->toDateTimeString(),
                'hotel_name' => $a->hotel_name,
                'company_name' => $a->company_name,
                'status' => $a->status,
                'payment_status' => $a->payment_status,
                'pbt_name' => $a->pbtDistrict?->district_name,
                'start_date' => optional($a->hotel?->license?->start_date)->toDateString(),
                'expiry_date' => optional($a->hotel?->license?->expiry_date)->toDateString(),
                'license_status' => $a->hotel?->license?->status,
                'license_number' => $a->hotel?->license?->license_number,
                'types' => $a->license_type_selected
                    ? collect([$a->license_type_selected])
                    : $a->licenseTypes->map(function ($t) {
                        return $t->aktiviti;
                    })->values(),
            ];
        })->values();

    return Inertia::render('e-lesen/Dashboard', [
        'dashboardStats' => [
            'total' => $totalApplications,
            'approved' => $approvedApplications,
            'rejected' => $rejectedApplications,
            'pending' => $pendingApplications,
        ],
        'recentApplications' => $recentApplications,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

// Profile routes
Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');

// Choose System page shown after login
Route::get('/choose-system', function () {
    License::query()
        ->whereNotNull('expiry_date')
        ->whereDate('expiry_date', '<', now()->startOfDay())
        ->where('status', '!=', 'Tamat Tempoh')
        ->update([
            'status' => 'Tamat Tempoh',
        ]);

    $user = auth()->user();
    $fiSejahteraBlocked = false;
    $fiSejahteraMessage = null;

    if ($user?->role === 'staff') {
        $staffHotel = HotelStaff::with('hotel.license')
            ->where('user_id', $user->id)
            ->first()?->hotel;

        $license = $staffHotel?->license;
        $hasExpiredLicense = $license
            && (
                $license->status === 'Tamat Tempoh'
                || ($license->expiry_date && Carbon::parse($license->expiry_date)->startOfDay()->lt(now()->startOfDay()))
            );

        if ($hasExpiredLicense) {
            $fiSejahteraBlocked = true;
            $fiSejahteraMessage = 'Lesen Penginapan Tamat Tempoh';
        }
    }

    return Inertia::render('ChooseSystem', [
        'fiSejahteraBlocked' => $fiSejahteraBlocked,
        'fiSejahteraMessage' => $fiSejahteraMessage,
    ]);
})->middleware(['auth', 'verified'])->name('choose.system');

// Fi Sejahtera Dashboard
Route::get('/fi-sejahtera/dashboard', [FiSejahteraController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('fi-sejahtera.dashboard');

// Fi Sejahtera Form
Route::get('/fi-sejahtera/apply', [FiSejahteraController::class, 'create'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.apply');

Route::post('/fi-sejahtera/apply', [FiSejahteraController::class, 'store'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.apply.store');

Route::get('/fi-sejahtera/payment', [FiSejahteraController::class, 'paymentCreate'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.payment');

Route::get('/fi-sejahtera/perbendaharaan', [FiSejahteraController::class, 'treasuryPayment'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.perbendaharaan');

Route::post('/fi-sejahtera/payment', [FiSejahteraController::class, 'paymentStore'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.payment.store');

Route::get('/fi-sejahtera/payment/start', [FiSejahteraController::class, 'paymentStart'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.payment.start');

Route::get('/fi-sejahtera/payment/pay', [FiSejahteraController::class, 'paymentPay'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.payment.pay');

Route::post('/fi-sejahtera/payment/submit-bkt', [FiSejahteraController::class, 'paymentSubmitToBkt'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.payment.submit-bkt');

Route::get('/fi-sejahtera/payment/return', [FiSejahteraController::class, 'handlePaymentReturn'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.payment.return');

Route::post('/fi-sejahtera/payment/callback', [FiSejahteraController::class, 'handlePaymentCallback'])
    ->name('fi-sejahtera.payment.callback');

Route::get('/fi-sejahtera/tax', [FiSejahteraController::class, 'taxList'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.tax');

Route::patch('/fi-sejahtera/tax/{taxSubmission}/verify', [FiSejahteraController::class, 'taxVerify'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.tax.verify');

Route::patch('/fi-sejahtera/tax/{taxSubmission}/reject', [FiSejahteraController::class, 'taxReject'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.tax.reject');

Route::get('/fi-sejahtera/staff', [FiSejahteraController::class, 'staffIndex'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.staff');

Route::get('/fi-sejahtera/guest', [FiSejahteraController::class, 'guestIndex'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.guest');

Route::get('/fi-sejahtera/guest/export-pdf', [FiSejahteraController::class, 'guestExportPdf'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.guest.export-pdf');

Route::post('/fi-sejahtera/staff', [FiSejahteraController::class, 'staffStore'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.staff.store');

Route::patch('/fi-sejahtera/staff/{hotelStaff}', [FiSejahteraController::class, 'staffUpdate'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.staff.update');

Route::delete('/fi-sejahtera/staff/{hotelStaff}', [FiSejahteraController::class, 'staffDestroy'])
    ->middleware(['auth'])
    ->name('fi-sejahtera.staff.destroy');

Route::get('/fi-sejahtera/settings/profile', function (Request $request) {
    return Inertia::render('fi sejahtera/settings/Profile', [
        'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
        'status' => $request->session()->get('status'),
    ]);
})->middleware(['auth'])->name('fi-sejahtera.settings.profile');

Route::get('/fi-sejahtera/settings/password', function () {
    return Inertia::render('fi sejahtera/settings/Password');
})->middleware(['auth', 'verified'])->name('fi-sejahtera.settings.password');

Route::get('/fi-sejahtera/settings/appearance', function () {
    return Inertia::render('fi sejahtera/settings/Appearance');
})->middleware(['auth', 'verified'])->name('fi-sejahtera.settings.appearance');


// License Application Routes
Route::get('/license/apply', [LicenseApplicationController::class, 'create'])
    ->middleware(['auth'])
    ->name('license.apply');

Route::get('/license/status', [LicenseApplicationController::class, 'status'])
    ->middleware(['auth'])
    ->name('license.status');

Route::post('/license/apply', [LicenseApplicationController::class, 'store'])
    ->middleware(['auth'])
    ->name('license.apply.store');

Route::patch('/license/applicant-info', [LicenseApplicationController::class, 'updateApplicantInfo'])
    ->middleware(['auth'])
    ->name('license.applicant-info.update');

Route::get('/license/additional-activities', [LicenseApplicationController::class, 'additionalActivities'])
    ->middleware(['auth'])
    ->name('license.additional-activities');

Route::get('/license/process-fee/start', [LicenseApplicationController::class, 'startProcessFeePayment'])
    ->middleware(['auth'])
    ->name('license.process-fee.start');

Route::get('/license/process-fee/status', [LicenseApplicationController::class, 'processFeeStatus'])
    ->middleware(['auth'])
    ->name('license.process-fee.status');

Route::get('/license/process-fee/return', [LicenseApplicationController::class, 'handleProcessFeeReturn'])
    ->middleware(['auth'])
    ->name('license.process-fee.return');

Route::post('/license/process-fee/callback', [LicenseApplicationController::class, 'handleProcessFeeCallback'])
    ->name('license.process-fee.callback');

Route::get('/license/payment/start', [LicenseApplicationController::class, 'startPayment'])
    ->middleware(['auth'])
    ->name('license.payment.start');

Route::get('/license/payment/return', [LicenseApplicationController::class, 'handlePaymentReturn'])
    ->middleware(['auth'])
    ->name('license.payment.return');

Route::post('/license/payment/callback', [LicenseApplicationController::class, 'handlePaymentCallback'])
    ->name('license.payment.callback');

Route::get('/license/renewals/payment/start', [LicenseApplicationController::class, 'startRenewalPayment'])
    ->middleware(['auth'])
    ->name('license.renewal.payment.start');

Route::get('/license/renewals/payment/return', [LicenseApplicationController::class, 'handleRenewalPaymentReturn'])
    ->middleware(['auth'])
    ->name('license.renewal.payment.return');

Route::post('/license/renewals/payment/callback', [LicenseApplicationController::class, 'handleRenewalPaymentCallback'])
    ->name('license.renewal.payment.callback');

// Admin License Application Routes
Route::get('/admin/license-applications', [LicenseApplicationController::class, 'adminIndex'])
    ->middleware(['auth'])
    ->name('admin.license.index');

Route::get('/admin/license-applications/{application}', [LicenseApplicationController::class, 'adminShow'])
    ->middleware(['auth'])
    ->name('admin.license.show');

Route::post('/admin/license-applications/{application}/approve', [LicenseApplicationController::class, 'approve'])
    ->middleware(['auth'])
    ->name('admin.license.approve');

Route::post('/admin/license-applications/{application}/block', [LicenseApplicationController::class, 'block'])
    ->middleware(['auth'])
    ->name('admin.license.block');

Route::post('/admin/license-applications/{application}/reject', [LicenseApplicationController::class, 'reject'])
    ->middleware(['auth'])
    ->name('admin.license.reject');

Route::get('/admin/license-applications/{application}/pdf', [LicenseApplicationController::class, 'generatePdf'])
    ->middleware(['auth'])
    ->name('admin.license.pdf');

Route::get('/admin/license-documents/{document}', [LicenseApplicationController::class, 'adminDocument'])
    ->middleware(['auth'])
    ->name('admin.license.document');

Route::get('/admin/license-renewals', [LicenseApplicationController::class, 'adminRenewalIndex'])
    ->middleware(['auth'])
    ->name('admin.license.renewals');

Route::get('/admin/license-renewals/{renewal}', [LicenseApplicationController::class, 'adminRenewalShow'])
    ->middleware(['auth'])
    ->name('admin.license.renewals.show');

Route::post('/admin/license-renewals/{renewal}/approve', [LicenseApplicationController::class, 'approveRenewal'])
    ->middleware(['auth'])
    ->name('admin.license.renewals.approve');

Route::post('/admin/license-renewals/{renewal}/reject', [LicenseApplicationController::class, 'rejectRenewal'])
    ->middleware(['auth'])
    ->name('admin.license.renewals.reject');

Route::get('/admin/license-additional-activities', [LicenseApplicationController::class, 'additionalActivityIndex'])
    ->middleware(['auth'])
    ->name('admin.license.additional-activities');

Route::post('/admin/license-additional-activities', [LicenseApplicationController::class, 'storeAdditionalActivity'])
    ->middleware(['auth'])
    ->name('admin.license.additional-activities.store');

Route::patch('/admin/license-additional-activities/{activity}', [LicenseApplicationController::class, 'updateAdditionalActivity'])
    ->middleware(['auth'])
    ->name('admin.license.additional-activities.update');

Route::delete('/admin/license-additional-activities/{activity}', [LicenseApplicationController::class, 'deleteAdditionalActivity'])
    ->middleware(['auth'])
    ->name('admin.license.additional-activities.destroy');

Route::get('/admin/license-additional-activities/{activity}', [LicenseApplicationController::class, 'additionalActivityShow'])
    ->middleware(['auth'])
    ->name('admin.license.additional-activities.show');

Route::post('/admin/license-additional-activities/{activity}/rates', [LicenseApplicationController::class, 'storeAdditionalActivityRate'])
    ->middleware(['auth'])
    ->name('admin.license.additional-activities.rates.store');
    
Route::put('/admin/license-additional-activities/rates/{rate}', [LicenseApplicationController::class, 'updateAdditionalActivityRate'])
    ->middleware(['auth'])
    ->name('admin.license.additional-activities.rates.update');

Route::delete('/admin/license-additional-activities/rates/{rate}', [LicenseApplicationController::class, 'deleteAdditionalActivityRate'])
    ->middleware(['auth'])
    ->name('admin.license.additional-activities.rates.destroy');

Route::get('/super-admin/admins', [LicenseApplicationController::class, 'adminListIndex'])
    ->middleware(['auth'])
    ->name('super-admin.admins');

Route::post('/super-admin/admins', [LicenseApplicationController::class, 'storeAdmin'])
    ->middleware(['auth']);

Route::patch('/super-admin/admins/{user}', [LicenseApplicationController::class, 'updateAdmin'])
    ->middleware(['auth']);

Route::delete('/super-admin/admins/{user}', [LicenseApplicationController::class, 'deleteAdmin'])
    ->middleware(['auth']);

require __DIR__.'/settings.php';
