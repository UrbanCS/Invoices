<?php

namespace Tests\Feature;

use App\Models\CleaningOrder;
use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\DailyRecord;
use App\Models\MonthlyInvoice;
use App\Models\UploadedDocument;
use App\Models\User;
use App\Services\MoneyFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceCatalogAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_delete_a_test_invoice_and_release_its_sources(): void
    {
        Storage::fake('public');

        $admin = $this->user('super_admin', 'admin-delete-invoice@test.com');
        $client = $this->client('Client test');
        $invoice = $this->invoice($client, $admin, 'TEST-DELETE-0726');
        $dailyRecord = DailyRecord::create([
            'client_id' => $client->id,
            'service_date' => '2026-07-01',
            'status' => 'invoiced',
            'created_by' => $admin->id,
        ]);
        $invoice->dailyRecords()->attach($dailyRecord);
        $order = CleaningOrder::create([
            'client_id' => $client->id,
            'monthly_invoice_id' => $invoice->id,
            'service_date' => '2026-07-01',
            'employee_name' => 'Julian',
            'status' => 'invoiced',
            'subtotal_cents' => 1000,
            'total_cents' => 1000,
        ]);

        Storage::disk('public')->put($invoice->pdf_path, 'pdf');
        Storage::disk('public')->put('monthly-invoices/'.$invoice->id.'/test.pdf', 'attachment');
        UploadedDocument::create([
            'client_id' => $client->id,
            'monthly_invoice_id' => $invoice->id,
            'file_path' => 'monthly-invoices/'.$invoice->id.'/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('monthly-invoices.destroy', $invoice))
            ->assertRedirect(route('monthly-invoices.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('monthly_invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('uploaded_documents', ['monthly_invoice_id' => $invoice->id]);
        $this->assertDatabaseHas('daily_records', [
            'id' => $dailyRecord->id,
            'status' => 'reviewed',
        ]);
        $this->assertDatabaseHas('cleaning_orders', [
            'id' => $order->id,
            'status' => 'reviewed',
            'monthly_invoice_id' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'monthly_invoice.deleted',
            'entity_id' => $invoice->id,
        ]);
        Storage::disk('public')->assertMissing($invoice->pdf_path);
        Storage::disk('public')->assertMissing('monthly-invoices/'.$invoice->id.'/test.pdf');
    }

    public function test_employee_cannot_delete_an_invoice(): void
    {
        $admin = $this->user('super_admin', 'admin-owner@test.com');
        $employee = $this->user('employee', 'employee-delete@test.com');
        $client = $this->client('Client protégé');
        $invoice = $this->invoice($client, $admin, 'TEST-PROTECTED-0726');

        $this->actingAs($employee)
            ->delete(route('monthly-invoices.destroy', $invoice))
            ->assertForbidden();

        $this->assertDatabaseHas('monthly_invoices', ['id' => $invoice->id]);
    }

    public function test_admin_can_copy_a_catalog_without_changing_the_target_tax_profile(): void
    {
        $admin = $this->user('super_admin', 'admin-copy-catalog@test.com');
        $source = $this->client('Holiday Inn Ottawa Dwtn - Parliament Hill', 'on_hst');
        $target = $this->client('Lord Elgin Hotel', 'qc_tps_tvq');

        ClientCategory::create([
            'client_id' => $source->id,
            'name' => 'Chemise / Shirt',
            'service_type' => 'laundry',
            'audience' => 'gentlemen',
            'sort_order' => 1,
            'default_price_cents' => 890,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $legacy = ClientCategory::create([
            'client_id' => $target->id,
            'name' => 'Ancien item',
            'service_type' => 'other',
            'audience' => 'unisex',
            'default_price_cents' => 100,
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.categories.copy', $source), [
                'target_client_ids' => [$target->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('client_categories', [
            'client_id' => $target->id,
            'name' => 'Chemise / Shirt',
            'service_type' => 'laundry',
            'audience' => 'gentlemen',
            'default_price_cents' => 890,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('client_categories', [
            'id' => $legacy->id,
            'is_active' => false,
        ]);
        $this->assertSame('qc_tps_tvq', $target->fresh()->tax_profile);
    }

    public function test_admin_can_apply_the_store_ottawa_price_template(): void
    {
        $admin = $this->user('super_admin', 'admin-store-template@test.com');
        $store = $this->client('Glebe tailoring', 'on_hst');

        $this->actingAs($admin)
            ->post(route('clients.categories.apply-store-template', $store), [
                'target_client_ids' => [$store->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('client_categories', [
            'client_id' => $store->id,
            'name' => 'Pants - Pressing only',
            'default_price_cents' => 1005,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('client_categories', [
            'client_id' => $store->id,
            'name' => 'Wedding dress (base price)',
            'default_price_cents' => 20000,
            'is_active' => true,
        ]);
        $this->assertSame('on_hst', $store->fresh()->tax_profile);
    }

    public function test_admin_can_add_the_employee_catalog_to_standard_hotels_without_replacing_existing_items(): void
    {
        $admin = $this->user('super_admin', 'admin-employee-template@test.com');
        $holidayInn = $this->client('Holiday Inn Ottawa Dwtn - Parliament Hill');
        $hiltonGarden = $this->client('Hilton Garden Inn');
        $marriott = $this->client('Ottawa Marriott Hotel');
        $fourPoints = $this->client('Four Points by Sheraton Gatineau-Ottawa');
        $hiltonLacLeamy = $this->client('Hilton Lac Leamy', 'qc_tps_tvq');
        $futureHotel = $this->client('Nouvel hôtel partenaire');
        $store = $this->client('Glebe tailoring');
        $hiltonLacLeamy->update(['invoice_style' => 'hotel']);
        $futureHotel->update(['invoice_style' => 'hotel']);

        ClientCategory::create([
            'client_id' => $holidayInn->id,
            'name' => 'Item existant',
            'service_type' => 'laundry',
            'audience' => 'gentlemen',
            'default_price_cents' => 890,
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.categories.apply-employee-template', $holidayInn))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $expectedPrices = [
            'Trouser' => 500,
            'Shirts' => 350,
            'Blouse' => 500,
            'Skirt' => 500,
            'Jacket' => 500,
            '2pc suit' => 1000,
            'Dress (and up)' => 1000,
            'Coat (and up)' => 2500,
        ];

        foreach ([$holidayInn, $hiltonGarden, $marriott, $fourPoints, $futureHotel] as $hotel) {
            $this->assertSame(
                8,
                $hotel->categories()->where('audience', 'employees')->where('is_active', true)->count(),
            );

            foreach ($expectedPrices as $name => $priceCents) {
                $this->assertDatabaseHas('client_categories', [
                    'client_id' => $hotel->id,
                    'name' => $name,
                    'service_type' => 'dry_cleaning',
                    'audience' => 'employees',
                    'default_price_cents' => $priceCents,
                    'is_active' => true,
                ]);
            }
        }

        $this->assertDatabaseHas('client_categories', [
            'client_id' => $holidayInn->id,
            'name' => 'Item existant',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('client_categories', [
            'client_id' => $hiltonLacLeamy->id,
            'audience' => 'employees',
        ]);
        $this->assertDatabaseMissing('client_categories', [
            'client_id' => $store->id,
            'audience' => 'employees',
        ]);
        $this->assertSame('EMPLOYÉS', ClientCategory::audienceLabel('employees'));

        $clientUser = $this->user('client', 'employee-catalog-client@test.com');
        $clientUser->update(['client_id' => $holidayInn->id]);

        $this->actingAs($clientUser)
            ->get(route('portal.orders.create'))
            ->assertOk()
            ->assertSee('EMPLOYÉS')
            ->assertSee('Trouser')
            ->assertSee('5,00 $');
    }

    public function test_employee_catalog_command_is_idempotent(): void
    {
        $holidayInn = $this->client('Holiday Inn Ottawa Downtown');

        $this->artisan('app:apply-employee-catalog', ['--force' => true])
            ->assertSuccessful();
        $this->artisan('app:apply-employee-catalog', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(
            8,
            $holidayInn->categories()->where('audience', 'employees')->where('is_active', true)->count(),
        );
        $this->assertDatabaseHas('client_categories', [
            'client_id' => $holidayInn->id,
            'name' => 'Dress (and up)',
            'default_price_cents' => 1000,
            'sort_order' => 7,
        ]);
    }

    public function test_pdf_uses_an_itemized_layout_for_a_large_catalog(): void
    {
        $admin = $this->user('super_admin', 'admin-pdf-layout@test.com');
        $client = $this->client('Grand catalogue');
        $categories = collect(range(1, 9))->map(fn (int $position) => [
            'id' => $position,
            'name' => 'Item '.$position,
            'service_type' => 'dry_cleaning',
            'audience' => 'unisex',
            'sort_order' => $position,
            'is_taxable' => true,
        ])->all();
        $invoice = $this->invoice($client, $admin, 'TEST-PDF-0726');
        $invoice->update(['category_snapshot' => $categories]);
        $invoice->entries()->create([
            'service_day' => 2,
            'client_category_id' => null,
            'category_name_snapshot' => 'Item 1',
            'amount_cents' => 2590,
            'item_details' => [[
                'label' => 'Complet 2 pc / Suit 2 pcs',
                'quantity' => 2,
                'unit_price_cents' => 1295,
                'total_cents' => 2590,
            ]],
            'source_type' => 'manual_monthly_grid',
        ]);

        $html = view('pdf.monthly-invoice', [
            'invoice' => $invoice->load(['client', 'entries', 'adjustments']),
            'settings' => null,
            'money' => app(MoneyFormatter::class),
        ])->render();

        $this->assertStringContainsString('Détail des items facturés', $html);
        $this->assertStringContainsString('Complet 2 pc / Suit 2 pcs', $html);
        $this->assertStringContainsString('12,95 $', $html);
        $this->assertStringContainsString('25,90 $', $html);
        $this->assertStringNotContainsString('Item 9</th>', $html);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function client(string $name, string $taxProfile = 'on_hst'): Client
    {
        return Client::create([
            'name' => $name,
            'tax_profile' => $taxProfile,
            'default_language' => 'fr',
        ]);
    }

    private function invoice(Client $client, User $creator, string $number): MonthlyInvoice
    {
        return MonthlyInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => $number,
            'invoice_month' => 7,
            'invoice_year' => 2026,
            'invoice_date' => '2026-07-29',
            'status' => 'approved',
            'source_mode' => 'manual_grid',
            'subtotal_cents' => 1000,
            'grand_total_cents' => 1130,
            'pdf_path' => 'invoices/2026/'.$number.'.pdf',
            'created_by' => $creator->id,
        ]);
    }
}
