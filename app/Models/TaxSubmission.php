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
        'submitted_at',
        'status',
        'verified_at',
        'verified_by',
        'remarks',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
        'year' => 'integer',
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
