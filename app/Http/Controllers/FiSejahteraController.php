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
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FiSejahteraController extends Controller
{
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

        $search = trim((string) $request->query('search', ''));
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

        $query = Booking::with([
            'guest:id,name,nationality,identity_number,email,phone_number',
            'hotel:id,name',
        ]);

        $hasHotel = $allowedHotelIds->isNotEmpty();

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
                'search' => $search,
                'hotel_id' => $selectedHotelId,
            ],
        ]);
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

    public function paymentCreate()
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

        $selectedHotelId = $ownedHotels
            ->first(fn (Hotel $hotel) => ! $this->isHotelLicenseExpired($hotel))?->id;

        return Inertia::render('fi sejahtera/Payment', [
            'ownedHotels' => $ownedHotels->map(fn (Hotel $hotel) => $this->mapHotelOption($hotel))->values(),
            'selectedHotelId' => $selectedHotelId,
        ]);
    }

    public function paymentStore(Request $request)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $this->ensureOwner();

        $validated = $request->validate([
            'submission_id' => ['nullable', 'integer', 'exists:tax_submissions,id'],
            'hotel_id' => ['required', 'integer', 'exists:hotels,id'],
            'month' => ['required', 'string', 'in:jan,feb,mar,apr,may,jun,jul,aug,sep,oct,nov,dec'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ]);

        $hotel = Hotel::query()
            ->where('user_id', auth()->id())
            ->where('id', (int) $validated['hotel_id'])
            ->with('license:id,hotel_id,status,expiry_date')
            ->first();

        if (! $hotel) {
            throw ValidationException::withMessages([
                'month' => 'Hotel tidak ditemui untuk akaun ini.',
            ]);
        }

        if ($this->isHotelLicenseExpired($hotel)) {
            throw ValidationException::withMessages([
                'hotel_id' => 'Lesen Penginapan Tamat Tempoh.',
            ]);
        }

        $existingApprovedOrPending = TaxSubmission::query()
            ->where('hotel_id', $hotel->id)
            ->where('month', $validated['month'])
            ->where('year', (int) $validated['year'])
            ->where('status', '!=', 'rejected')
            ->exists();

        if ($existingApprovedOrPending) {
            throw ValidationException::withMessages([
                'month' => 'Penghantaran untuk bulan dan tahun ini telah dibuat.',
            ]);
        }

        $targetSubmissionId = isset($validated['submission_id'])
            ? (int) $validated['submission_id']
            : null;

        $rejectedSubmission = null;

        if ($targetSubmissionId) {
            $rejectedSubmission = TaxSubmission::query()
                ->where('id', $targetSubmissionId)
                ->where('hotel_id', $hotel->id)
                ->where('month', $validated['month'])
                ->where('year', (int) $validated['year'])
                ->where('status', 'rejected')
                ->first();

            if (! $rejectedSubmission) {
                throw ValidationException::withMessages([
                    'month' => 'Rekod penghantaran semula tidak sah atau telah berubah. Sila cuba semula dari senarai cukai.',
                ]);
            }
        } else {
            $rejectedSubmission = TaxSubmission::query()
                ->where('hotel_id', $hotel->id)
                ->where('month', $validated['month'])
                ->where('year', (int) $validated['year'])
                ->where('status', 'rejected')
                ->latest('id')
                ->first();
        }

        $fileRules = [
            'payment_proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'guest_report' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        if ($rejectedSubmission) {
            $fileRules = [
                'payment_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
                'guest_report' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ];
        }

        $fileValidated = $request->validate($fileRules);

        if ($rejectedSubmission && ! $request->hasFile('payment_proof') && ! $request->hasFile('guest_report')) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Sila muat naik sekurang-kurangnya satu fail untuk hantar semula.',
            ]);
        }

        if ($rejectedSubmission) {
            $paymentProofPath = $rejectedSubmission->payment_proof;
            $guestReportPath = $rejectedSubmission->guest_report;

            if ($request->hasFile('payment_proof')) {
                if ($rejectedSubmission->payment_proof) {
                    Storage::disk('public')->delete($rejectedSubmission->payment_proof);
                }

                $paymentProofPath = $request->file('payment_proof')->store('tax-submissions/payment-proofs', 'public');
            }

            if ($request->hasFile('guest_report')) {
                if ($rejectedSubmission->guest_report) {
                    Storage::disk('public')->delete($rejectedSubmission->guest_report);
                }

                $guestReportPath = $request->file('guest_report')->store('tax-submissions/guest-reports', 'public');
            }

            $rejectedSubmission->update([
                'payment_proof' => $paymentProofPath,
                'guest_report' => $guestReportPath,
                'submitted_at' => now(),
                'status' => 'submitted',
                'verified_at' => null,
                'verified_by' => null,
                'remarks' => null,
            ]);

            return redirect()->route('fi-sejahtera.payment')->with('success', 'Penghantaran semula berjaya. Rekod menunggu semakan semula.');
        }

        $paymentProofPath = $fileValidated['payment_proof']->store('tax-submissions/payment-proofs', 'public');
        $guestReportPath = $fileValidated['guest_report']->store('tax-submissions/guest-reports', 'public');

        TaxSubmission::create([
            'hotel_id' => $hotel->id,
            'month' => $validated['month'],
            'year' => (int) $validated['year'],
            'payment_proof' => $paymentProofPath,
            'guest_report' => $guestReportPath,
            'submitted_at' => now(),
            'status' => 'submitted',
            'verified_at' => null,
            'verified_by' => null,
            'remarks' => null,
        ]);

        return redirect()->route('fi-sejahtera.payment')->with('success', 'Bukti pembayaran berjaya dihantar.');
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
                    'payment_proof_url' => Storage::url($submission->payment_proof),
                    'guest_report_url' => Storage::url($submission->guest_report),
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
            'isAdmin' => $this->isBktAdmin($user),
        ]);
    }

    public function taxVerify(TaxSubmission $taxSubmission)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $this->ensureBktAdmin();

        if ($taxSubmission->status === 'verified') {
            return redirect()->route('fi-sejahtera.tax')->with('error', 'Penghantaran cukai yang telah disahkan tidak boleh dikemas kini.');
        }

        $taxSubmission->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => auth()->id(),
            'remarks' => null,
        ]);

        return redirect()->route('fi-sejahtera.tax')->with('success', 'Penghantaran cukai berjaya disahkan.');
    }

    public function taxReject(Request $request, TaxSubmission $taxSubmission)
    {
        if ($redirect = $this->enforceFiSejahteraAccess()) {
            return $redirect;
        }

        $this->ensureBktAdmin();

        if ($taxSubmission->status === 'verified') {
            return redirect()->route('fi-sejahtera.tax')->with('error', 'Penghantaran cukai yang telah disahkan tidak boleh dikemas kini.');
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
