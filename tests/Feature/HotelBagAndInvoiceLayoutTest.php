<?php

namespace Tests\Feature;

use App\Models\CleaningOrder;
use App\Models\Client;
use App\Models\MonthlyInvoice;
use App\Models\User;
use App\Services\InvoicePresentationService;
use App\Services\MoneyFormatter;
use App\Services\SharedCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelBagAndInvoiceLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_bag_rollout_is_idempotent_and_preserves_other_catalogues(): void
    {
        $hotel = $this->hotel();
        $lac = $this->hotel('Hilton Lac Leamy');
        $store = $this->hotel('Commerce');
        $store->update(['invoice_style' => 'standard']);
        $paid = $hotel->categories()->create(['name' => 'Suit', 'default_price_cents' => 2290, 'is_active' => true]);
        $this->artisan('app:apply-hotel-bag', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('client_categories', 1);
        foreach ([1, 2] as $run) {
            $this->artisan('app:apply-hotel-bag', ['--force' => true])->assertSuccessful();
            $this->assertSame(2, $hotel->categories()->where('name', 'Sac / Bag')->count());
            $this->assertSame(2, $lac->categories()->where('name', 'Sac / Bag')->count());
        }
        $this->assertSame(2290, $paid->fresh()->default_price_cents);
        $this->assertTrue($paid->fresh()->is_active);
        $this->assertSame(0, $store->categories()->count());
    }

    public function test_free_items_survive_manual_invoice_creation_and_editing_for_both_types(): void
    {
        $hotel = $this->hotel();
        app(SharedCatalogService::class)->applyHotelBag($hotel);
        $admin = $this->user('super_admin');
        foreach (['employee' => 'employees', 'hotel_guest' => 'unisex'] as $type => $audience) {
            $bag = $hotel->categories()->where('audience', $audience)->firstOrFail();
            $data = $this->invoiceData($hotel, 'FREE-'.$type);
            $data['grid'] = [3 => [$bag->id => '0,00'], 4 => [$bag->id => '']];
            $data['details'] = [3 => [$bag->id => [$this->detail($type, 'Sac / Bag', 2, '0,00')]]];
            $this->actingAs($admin)->post(route('monthly-invoices.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
            $invoice = MonthlyInvoice::where('invoice_number', 'FREE-'.$type)->firstOrFail();
            $this->assertCount(1, $invoice->entries);
            $this->assertSame(0, $invoice->grand_total_cents);
            $this->assertSame('2', $invoice->entries->first()->item_details[0]['quantity']);
            $this->actingAs($admin)->put(route('monthly-invoices.update', $invoice), $data)->assertSessionHasNoErrors();
            $this->assertCount(1, $invoice->fresh()->entries);
            $this->actingAs($admin)->get(route('monthly-invoices.show', $invoice))->assertOk()->assertSee('Sac / Bag');
        }
    }

    public function test_zero_amount_does_not_create_empty_or_paid_catalogue_entries(): void
    {
        $hotel = $this->hotel();
        $paid = $hotel->categories()->create(['name' => 'Suit', 'default_price_cents' => 2290, 'is_active' => true]);
        $data = $this->invoiceData($hotel, 'NO-ZERO-PAID');
        $data['grid'] = [3 => [$paid->id => '0,00']];
        $data['details'] = [3 => [$paid->id => [$this->detail('hotel_guest', 'Suit', 2, '0,00')]]];
        $this->actingAs($this->user('super_admin'))->post(route('monthly-invoices.store'), $data)->assertSessionHasErrors('grid');
        $this->assertDatabaseCount('monthly_invoice_entries', 0);
    }

    public function test_portal_accepts_free_bags_and_keeps_server_prices_and_audience_isolation(): void
    {
        $hotel = $this->hotel();
        app(SharedCatalogService::class)->applyHotelBag($hotel);
        $user = $this->user('client', $hotel);
        foreach (['employee' => 'employees', 'hotel_guest' => 'unisex'] as $type => $audience) {
            $bag = $hotel->categories()->where('audience', $audience)->firstOrFail();
            $data = ['service_date' => '2026-09-03', 'order_type' => $type,
                'new_employee_name' => 'Test', 'employee_tag_number' => '0099', 'department_number' => '01',
                'guest_name' => 'Test', 'guest_tag_number' => '0099', 'room_number' => '477',
                'quantities' => [$bag->id => 2], 'unit_price' => 1000];
            $this->flushSession();
            $this->actingAs($user)->post(route('portal.orders.store'), $data)->assertSessionHasNoErrors();
            $order = CleaningOrder::latest('id')->firstOrFail();
            $this->assertSame(0, $order->total_cents);
            $this->assertSame(0, $order->items->first()->unit_price_cents);
            $data['order_type'] = $type === 'employee' ? 'hotel_guest' : 'employee';
            $this->actingAs($user)->post(route('portal.orders.store'), $data)->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('cleaning_orders', 2);
        $admin = $this->user('super_admin');
        foreach (CleaningOrder::all() as $order) {
            $order->update(['status' => 'reviewed']);
            $this->actingAs($admin)->post(route('cleaning-orders.create-invoice', $order))->assertSessionHasNoErrors();
            $invoice = MonthlyInvoice::findOrFail($order->fresh()->monthly_invoice_id);
            $this->assertSame(0, $invoice->grand_total_cents);
            $this->assertCount(1, $invoice->entries);
            $this->assertSame('Sac / Bag', $invoice->entries->first()->item_details[0]['label']);
        }
    }

    public function test_free_bag_and_paid_item_are_saved_together_without_changing_the_total(): void
    {
        $hotel = $this->hotel();
        app(SharedCatalogService::class)->applyHotelBag($hotel);
        $bag = $hotel->categories()->where('audience', 'unisex')->firstOrFail();
        $paid = $hotel->categories()->create(['name' => 'Suit', 'audience' => 'unisex', 'default_price_cents' => 2290, 'is_active' => true, 'is_taxable' => true]);
        $data = $this->invoiceData($hotel, 'MIXED');
        $data['grid'] = [3 => [$bag->id => '0,00', $paid->id => '45,80']];
        $data['details'] = [3 => [
            $bag->id => [$this->detail('hotel_guest', 'Sac / Bag', 1, '0,00')],
            $paid->id => [$this->detail('hotel_guest', 'Suit', 2, '22,90')],
        ]];
        $this->actingAs($this->user('super_admin'))->post(route('monthly-invoices.store'), $data)->assertSessionHasNoErrors();
        $invoice = MonthlyInvoice::where('invoice_number', 'MIXED')->firstOrFail();
        $this->assertCount(2, $invoice->entries);
        $this->assertSame(4580, $invoice->subtotal_cents);
        $presentation = app(InvoicePresentationService::class);
        $group = $presentation->groupedLineItems($presentation->lineItems($invoice))->sole();
        $this->assertCount(2, $group['items']);
        $this->assertSame(4580, $group['total_cents']);
    }

    public function test_group_totals_labels_and_bottom_adjustments_in_french_and_english(): void
    {
        $hotel = $this->hotel();
        $admin = $this->user('super_admin');
        $invoice = MonthlyInvoice::create([...$this->invoiceData($hotel, 'LAYOUT'), 'created_by' => $admin->id, 'status' => 'approved', 'subtotal_cents' => 7370, 'grand_total_cents' => 8215]);
        $invoice->entries()->create(['service_day' => 3, 'category_name_snapshot' => 'Items', 'amount_cents' => 7370, 'source_type' => 'manual_monthly_grid',
            'item_details' => [
                [...$this->detail('hotel_guest', '2 pcs suit', 2, '22.90'), 'unit_price_cents' => 2290, 'total_cents' => 4580],
                [...$this->detail('hotel_guest', 'Blouse', 2, '13.95'), 'unit_price_cents' => 1395, 'total_cents' => 2790],
                [...$this->detail('hotel_guest', 'Sac / Bag', 1, '0'), 'unit_price_cents' => 0, 'total_cents' => 0],
            ]]);
        foreach (['discount' => 'Rabais test', 'credit' => 'Crédit test', 'fee' => 'Frais test'] as $type => $label) {
            $invoice->adjustments()->create(['type' => $type, 'label' => $label, 'amount_cents' => 100]);
        }
        $invoice->load(['client', 'entries', 'adjustments']);
        $presentation = app(InvoicePresentationService::class);
        $lines = $presentation->lineItems($invoice);
        $groups = $presentation->groupedLineItems($lines);
        $this->assertCount(1, $groups);
        $this->assertSame(7370, $groups->first()['total_cents']);
        // Same day but another reference or person must remain separate.
        $this->assertCount(3, $presentation->groupedLineItems($lines->concat([
            [...$lines->first(), 'reference_number' => '0100'],
            [...$lines->first(), 'person_name' => 'Someone else'],
        ])));
        foreach (['fr' => '73,70 $', 'en' => '$73.70'] as $lang => $total) {
            $invoice->client->default_language = $lang;
            $html = view('pdf.monthly-invoice', ['invoice' => $invoice, 'settings' => null, 'money' => app(MoneyFormatter::class),
                'groupedLineItems' => $groups, 'dailyBillingTotals' => $presentation->dailyBillingTotals($lines),
                'billingSubtotals' => $presentation->billingSubtotals($lines)])->render();
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);
            $cells = $xpath->query('//td[contains(@class,"group-total")]');
            $this->assertCount(1, $cells);
            $this->assertSame($total, trim($cells->item(0)->textContent));
            $this->assertStringNotContainsString('Dry Cleaning', $html);
            $this->assertLessThan(strpos($html, 'Rabais test'), strpos($html, 'Sac / Bag'));
            $this->assertStringContainsString('Crédit test', $html);
            $this->assertStringContainsString('Frais test', $html);
        }
        $response = $this->actingAs($admin)->get(route('monthly-invoices.show', $invoice))->assertOk();
        $response->assertSeeInOrder(['Sac / Bag', 'Rabais, crédits et frais', 'Rabais test', 'Crédit test', 'Frais test']);
        $response->assertDontSee('lg:grid-cols-[1fr_340px]', false);
    }

    private function hotel(string $name = 'Test Hotel'): Client
    {
        return Client::create(['name' => $name, 'invoice_style' => 'hotel', 'is_active' => true, 'tax_profile' => 'on_hst', 'default_language' => 'fr']);
    }

    private function user(string $role, ?Client $client = null): User
    {
        return User::create(['name' => 'Test', 'email' => $role.'@example.test', 'password' => 'password', 'role' => $role, 'client_id' => $client?->id]);
    }

    private function invoiceData(Client $client, string $number): array
    {
        return ['client_id' => $client->id, 'invoice_number' => $number, 'invoice_month' => 9, 'invoice_year' => 2026, 'invoice_date' => '2026-09-10', 'source_mode' => 'manual_grid'];
    }

    private function detail(string $type, string $label, int $quantity, string $unit): array
    {
        return ['billing_type' => $type, 'label' => $label, 'quantity' => $quantity, 'unit_price' => $unit, 'person_name' => 'Test Person', 'reference_number' => '0099', 'room_number' => $type === 'hotel_guest' ? '477' : null];
    }
}
