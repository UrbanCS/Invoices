<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadedDocument extends Model
{
    protected $fillable = [
        'client_id', 'monthly_invoice_id', 'daily_record_id', 'file_path', 'original_name',
        'mime_type', 'size_bytes', 'notes', 'uploaded_by',
    ];
}
