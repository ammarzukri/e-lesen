<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenseDocument extends Model
{
    protected $table = 'license_document';

    protected $fillable = [
        'license_application_id',
        'document_type',
        'file_path',
        'upload_at',
    ];

    protected $casts = [
        'upload_at' => 'datetime',
    ];

    public function licenseApplication()
    {
        return $this->belongsTo(LicenseApplication::class, 'license_application_id');
    }
}
