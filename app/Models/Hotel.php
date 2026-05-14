<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    protected $fillable = [
        'user_id',
        'license_application_id',
        'pbt_name',
        'company_name',
        'category',
        'premises_location',
        'name',
        'address',
        'postcode',
        'state',
        'district',
        'phone',
        'registration_number',
        'registration_date',
        'registration_expiry_date',
        'employee_malay',
        'employee_chinese',
        'employee_indian',
        'employee_others',
        'operation_start',
        'operation_end',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function licenseApplication()
    {
        return $this->belongsTo(LicenseApplication::class, 'license_application_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function staff()
    {
        return $this->hasMany(HotelStaff::class);
    }

    public function license()
    {
        return $this->hasOne(License::class, 'hotel_id');
    }

    public function taxSubmissions()
    {
        return $this->hasMany(TaxSubmission::class);
    }
}
