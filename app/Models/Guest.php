<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $fillable = [
        'name',
        'nationality',
        'identity_type',
        'identity_number',
        'email',
        'phone_number',
        'country',
        'state',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
