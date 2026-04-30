<?php

namespace App\Policies;

use App\Models\DailyRecord;
use App\Models\User;

class DailyRecordPolicy
{
    public function view(User $user, DailyRecord $dailyRecord): bool
    {
        return $user->canManage();
    }

    public function manage(User $user): bool
    {
        return $user->canManage();
    }
}
