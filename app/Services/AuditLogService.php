<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function record(string $action, Model $model, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'entity_type' => $model::class,
            'entity_id' => $model->getKey(),
            'before_data' => $before,
            'after_data' => $after ?? $model->fresh()?->toArray(),
            'ip_address' => Request::ip(),
        ]);
    }
}
