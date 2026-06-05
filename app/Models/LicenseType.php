<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseType extends Model
{
    protected $table = 'license_type';

    protected $fillable = [
        'license_application_id',
        'aktiviti',
        'keluasan',
        'unit_bilik',
    ];

    public function licenseApplication()
    {
        return $this->belongsTo(LicenseApplication::class, 'license_application_id');
    }
}
