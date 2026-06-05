<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdditionalActivityRate extends Model
{
    protected $fillable = [
        'additional_activity_id',
        'type_name',
        'min_area',
        'max_area',
        'amount',
    ];

    public function activity()
    {
        return $this->belongsTo(AdditionalActivity::class);
    }
}