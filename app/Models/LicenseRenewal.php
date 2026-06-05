<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseRenewal extends Model
{
    protected $table = 'license_renewal';

    protected $fillable = [
        'license_id',
        'user_id',
        'status',
        'payment_status',
        'payment_amount',
        'payment_billcode',
        'payment_attempted_at',
        'payment_paid_at',
        'current_expiry_date',
        'renewed_until_date',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'payment_attempted_at' => 'datetime',
        'payment_paid_at' => 'datetime',
        'current_expiry_date' => 'date',
        'renewed_until_date' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function license()
    {
        return $this->belongsTo(License::class, 'license_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
