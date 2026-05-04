<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\DailyRecord;
use App\Models\InvoiceAdjustment;
use App\Models\MonthlyInvoice;
use App\Models\MonthlyInvoiceEntry;
use App\Models\Payment;
use App\Models\UploadedDocument;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanDemoData extends Command
{
    protected $signature = 'app:clean-demo-data {--force : Confirmer la suppression sans question}';

    protected $description = 'Supprime les clients, factures, registres et comptes client démo pour repartir avec une base propre.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Supprimer les données démo et garder seulement le compte admin?')) {
            return self::SUCCESS;
        }

        DB::transaction(function () {
            UploadedDocument::query()->delete();
            Payment::query()->delete();
            InvoiceAdjustment::query()->delete();
            MonthlyInvoiceEntry::query()->delete();
            DB::table('monthly_invoice_daily_record')->delete();
            MonthlyInvoice::query()->delete();
            DailyRecord::query()->delete();
            DB::table('daily_record_items')->delete();
            DB::table('client_categories')->delete();
            User::where('role', 'client')->delete();
            User::where('role', 'employee')->delete();
            Client::query()->delete();
            AuditLog::query()->delete();
        });

        $this->info('Données démo supprimées. Le compte admin est conservé.');

        return self::SUCCESS;
    }
}
