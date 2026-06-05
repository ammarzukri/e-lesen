<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxSubmission extends Model
{
    protected $fillable = [
        'hotel_id',
        'month',
        'year',
        'payment_proof',
        'guest_report',
        'hotel_guest_list',
        'payment_status',
        'payment_amount',
        'payment_billcode',
        'payment_attempted_at',
        'payment_paid_at',
        'submitted_at',
        'status',
        'verified_at',
        'verified_by',
        'remarks',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'payment_attempted_at' => 'datetime',
        'payment_paid_at' => 'datetime',
        'year' => 'integer',
        'payment_amount' => 'decimal:2',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
