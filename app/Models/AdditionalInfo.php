<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalInfo extends Model
{
    protected $table = 'additional_info';

    protected $fillable = [
        'license_application_id',
        'additional_activity_id',
        'additional_activity_rate_id',
        'activity_type',
        'jenis',
        'activity_name',
        'type_name',
        'keluasan_mps',
        'amount',
    ];

    public function licenseApplication()
    {
        return $this->belongsTo(LicenseApplication::class, 'license_application_id');
    }
    
    public function activity()
    {
        return $this->belongsTo(AdditionalActivity::class, 'additional_activity_id');
    }

    public function rate()
    {
        return $this->belongsTo(
            AdditionalActivityRate::class,
            'additional_activity_rate_id'
        );
    }
}
