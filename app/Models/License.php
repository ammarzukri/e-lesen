<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $table = 'license';

    protected $fillable = [
        'hotel_id',
        'license_number',
        'start_date',
        'expiry_date',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }

    public function renewals()
    {
        return $this->hasMany(LicenseRenewal::class, 'license_id');
    }

    public function latestRenewal()
    {
        return $this->hasOne(LicenseRenewal::class, 'license_id')->latestOfMany();
    }
}
