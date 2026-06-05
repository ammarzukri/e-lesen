<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelStaff extends Model
{
    protected $table = 'hotel_staff';

    protected $fillable = [
        'user_id',
        'hotel_id',
        'position',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
