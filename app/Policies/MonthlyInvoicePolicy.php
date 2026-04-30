<?php

namespace App\Policies;

use App\Models\MonthlyInvoice;
use App\Models\User;

class MonthlyInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, MonthlyInvoice $invoice): bool
    {
        return $user->canManage() || ($user->isClientUser() && $user->client_id === $invoice->client_id);
    }

    public function manage(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, MonthlyInvoice $invoice): bool
    {
        return $user->canManage();
    }

    public function delete(User $user, MonthlyInvoice $invoice): bool
    {
        return $user->isSuperAdmin();
    }
}
