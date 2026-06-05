<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = [
        'district_name',
        'district_code',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function licenseApplications(): HasMany
    {
        return $this->hasMany(LicenseApplication::class);
    }
}
