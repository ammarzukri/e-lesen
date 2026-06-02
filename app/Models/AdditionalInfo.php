<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalInfo extends Model
{
    protected $table = 'additional_info';

    protected $fillable = [
        'license_application_id',
        'activity_type',
        'jenis',
        'keluasan_mps',
    ];

    public function licenseApplication()
    {
        return $this->belongsTo(LicenseApplication::class, 'license_application_id');
    }
}
