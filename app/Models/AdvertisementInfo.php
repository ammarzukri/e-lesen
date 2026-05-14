<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvertisementInfo extends Model
{
    protected $table = 'advertisement_info';

    protected $fillable = [
        'license_application_id',
        'type',
        'structure',
        'length',
        'width',
        'number_of_ads',
    ];

    public function licenseApplication()
    {
        return $this->belongsTo(LicenseApplication::class, 'license_application_id');
    }
}
