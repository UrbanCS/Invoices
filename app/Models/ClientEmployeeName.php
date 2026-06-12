<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientEmployeeName extends Model
{
    protected $fillable = ['client_id', 'name'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
