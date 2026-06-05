<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseProcessFeePayment extends Model
{
    protected $table = 'license_process_fee_payment';

    protected $fillable = [
        'user_id',
        'license_application_id',
        'payment_status',
        'payment_amount',
        'payment_billcode',
        'payment_external_reference',
        'payment_attempted_at',
        'payment_paid_at',
        'consumed_at',
    ];

    protected $casts = [
        'payment_attempted_at' => 'datetime',
        'payment_paid_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function application()
    {
        return $this->belongsTo(LicenseApplication::class, 'license_application_id');
    }
}
