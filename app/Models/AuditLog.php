<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'before_data', 'after_data', 'ip_address'];

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array'];
    }
}
