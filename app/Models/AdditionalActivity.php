<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalActivity extends Model
{
    protected $fillable = [
        'district_id',
        'activity_name',
        'status',
    ];

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function rates()
    {
        return $this->hasMany(AdditionalActivityRate::class);
    }
}