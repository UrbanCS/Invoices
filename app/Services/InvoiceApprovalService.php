<?php

namespace App\Services;

use App\Models\MonthlyInvoice;

class InvoiceApprovalService
{
    public function __construct(private readonly AuditLogService $audit)
    {
    }

    public function approve(MonthlyInvoice $invoice): void
    {
        if ($invoice->status === 'approved') {
            return;
        }

        $invoice->update(['status' => 'approved']);
        $invoice->dailyRecords()->update(['status' => 'invoiced']);
        $this->audit->record('monthly_invoice.approved', $invoice);
    }
}
