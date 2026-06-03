<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseApplication extends Model
{
    protected $table = 'license_application';

    protected $fillable = [
        'name',
        'user_id',
        'pbt_name',
        'status',
        'remarks',
        'email',
        'company_name',
        'hotel_name',
        'company_address',
        'company_postcode',
        'company_state',
        'company_district',
        'company_phone',
        'company_registration_number',
        'company_registration_date',
        'company_registration_expiry_date',
        'company_category',
        'company_premises_location',
        'license_type_selected',
        'room_count',
        'employee_malay',
        'employee_chinese',
        'employee_indian',
        'employee_others',
        'company_operation_start',
        'company_operation_end',
        'company_address_hq',
        'company_postcode_hq',
        'company_state_hq',
        'company_district_hq',
        'company_phone_hq',
        'payment_status',
        'payment_amount',
        'payment_billcode',
        'payment_attempted_at',
        'payment_paid_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function licenseTypes()
    {
        return $this->hasMany(LicenseType::class, 'license_application_id');
    }

    public function advertisementInfos()
    {
        return $this->hasMany(AdvertisementInfo::class, 'license_application_id');
    }

    public function additionalInfos()
    {
        return $this->hasMany(AdditionalInfo::class, 'license_application_id');
    }

    public function documents()
    {
        return $this->hasMany(LicenseDocument::class, 'license_application_id');
    }

    public function hotel()
    {
        return $this->hasOne(Hotel::class, 'license_application_id');
    }

    public function processFeePayment()
    {
        return $this->hasOne(LicenseProcessFeePayment::class, 'license_application_id');
    }
}
