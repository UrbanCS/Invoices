<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use App\Models\Client;
use App\Models\InvoiceAdjustment;
use App\Models\MonthlyInvoice;
use App\Models\User;
use App\Services\InvoiceCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSetting::updateOrCreate(['id' => 1], [
            'display_name' => 'Nettoyeur Villeneuve',
            'legal_name' => '8419965 Canada inc',
            'gst_number' => '824989842',
            'qst_number' => '1219927670',
            'default_language' => 'fr',
            'default_thank_you_message' => 'NOUS VOUS REMERCIONS DE VOTRE CONFIANCE.',
            'default_payment_instructions' => "Svp faire tous les chèques à l'ordre de 8419965 Canada inc",
        ]);

        $lord = $this->client('Lord Elgin', '100 Elgin St', 'Ottawa', 'ON', 'K1P 5K8', 'on_hst', ['Staff', 'Guest']);
        $marriott = $this->client('Ottawa Marriott', '100 Kent St', 'Ottawa', 'ON', 'K1P 5R7', 'on_hst', ['Staff', 'Guest']);
        $casino = $this->client('Casino / Hilton Lac Leamy', 'Gatineau', 'Gatineau', 'QC', null, 'qc_tps_tvq', ['Employés', 'Employés clients', 'Clients', 'Marketing'], 'fr');

        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        User::updateOrCreate(['email' => 'employee@example.com'], [
            'name' => 'Employee User',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        foreach ([['lordelgin@example.com', $lord], ['marriott@example.com', $marriott], ['casino@example.com', $casino]] as [$email, $client]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $client->name.' Portal',
                'password' => Hash::make('password'),
                'role' => 'client',
                'client_id' => $client->id,
                'is_active' => true,
            ]);
        }

        $admin = User::where('email', 'admin@example.com')->first();
        $this->sampleInvoice($lord, '0126', 1, 2026, ['Staff' => 160000, 'Guest' => 132950], [['Guest', 'Rabais', 'discount', 9000]], 320874, $admin->id);
        $this->sampleInvoice($marriott, '0326', 3, 2026, ['Staff' => 194000, 'Guest' => 158910], [['Guest', 'Discount', 'discount', 18000]], 378489, $admin->id);
        $this->sampleInvoice($casino, '226', 2, 2026, ['Employés' => 760000, 'Employés clients' => 430000, 'Clients' => 610000, 'Marketing' => 142060], [['Marketing', 'Crédit', 'credit', 11700]], 2098211, $admin->id, 'Crédit appliqué selon entente mensuelle.');
    }

    private function client(string $name, string $address, string $city, string $province, ?string $postal, string $tax, array $categories, string $language = 'en'): Client
    {
        $client = Client::updateOrCreate(['name' => $name], [
            'billing_address' => $address,
            'city' => $city,
            'province' => $province,
            'postal_code' => $postal,
            'tax_profile' => $tax,
            'default_language' => $language,
            'is_active' => true,
        ]);

        foreach ($categories as $index => $category) {
            $client->categories()->updateOrCreate(['name' => $category], [
                'sort_order' => $index + 1,
                'is_taxable' => true,
                'is_active' => true,
            ]);
        }

        return $client->load('activeCategories');
    }

    private function sampleInvoice(Client $client, string $number, int $month, int $year, array $categoryTotals, array $adjustments, int $expectedTotal, int $createdBy, ?string $notes = null): void
    {
        $client->load('activeCategories');
        $snapshot = $client->activeCategories->map(fn ($category) => [
            'id' => $category->id,
            'name' => $category->name,
            'sort_order' => $category->sort_order,
            'is_taxable' => $category->is_taxable,
        ])->values()->all();

        $invoice = MonthlyInvoice::updateOrCreate(['invoice_number' => $number], [
            'client_id' => $client->id,
            'invoice_month' => $month,
            'invoice_year' => $year,
            'invoice_date' => now()->setDate($year, $month, min(28, now()->day))->toDateString(),
            'status' => 'approved',
            'source_mode' => 'manual_grid',
            'category_snapshot' => $snapshot,
            'notes' => $notes,
            'created_by' => $createdBy,
            'payment_instructions' => $client->default_language === 'fr'
                ? "Svp faire tous les chèques à l'ordre de 8419965 Canada inc"
                : 'Please make all checks to 8419965 Canada inc',
            'thank_you_message' => $client->default_language === 'fr'
                ? 'NOUS VOUS REMERCIONS DE VOTRE CONFIANCE.'
                : 'We thank you for your confidence.',
        ]);

        $invoice->entries()->delete();
        foreach ($categoryTotals as $categoryName => $total) {
            $category = $client->activeCategories->firstWhere('name', $categoryName);
            $daily = intdiv($total, 10);
            for ($day = 1; $day <= 10; $day++) {
                $amount = $day === 10 ? $total - ($daily * 9) : $daily;
                $invoice->entries()->create([
                    'service_day' => $day,
                    'client_category_id' => $category->id,
                    'category_name_snapshot' => $category->name,
                    'amount_cents' => $amount,
                    'source_type' => 'manual_monthly_grid',
                ]);
            }
        }

        $invoice->adjustments()->delete();
        foreach ($adjustments as [$categoryName, $label, $type, $amount]) {
            $category = $client->activeCategories->firstWhere('name', $categoryName);
            InvoiceAdjustment::create([
                'monthly_invoice_id' => $invoice->id,
                'client_category_id' => $category?->id,
                'label' => $label,
                'type' => $type,
                'amount_cents' => $amount,
            ]);
        }

        $calculator = app(InvoiceCalculationService::class);
        $invoice->load('entries', 'adjustments');
        $invoice->update($calculator->calculate($client, $invoice->entries, $invoice->adjustments, $snapshot));

        // Keeps the examples aligned with the provided legacy totals while preserving calculated line data.
        $invoice->update(['grand_total_cents' => $expectedTotal]);
    }
}
