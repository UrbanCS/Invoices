<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessSetting extends Model
{
    protected $fillable = [
        'legal_name', 'display_name', 'address', 'city', 'province', 'postal_code', 'phone', 'email',
        'gst_number', 'qst_number', 'logo_path', 'default_payment_instructions',
        'default_thank_you_message', 'default_language',
    ];
}
