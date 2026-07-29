<?php

namespace Tests\Feature;

use App\Models\CleaningOrder;
use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\MonthlyInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleaningOrderInvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_correct_own_submitted_order_without_changing_the_fixed_price(): void
    {
        $client = Client::create([
            'name' => 'Hotel Metcalfe',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $category = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Habit',
            'default_price_cents' => 725,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Client Metcalfe',
            'email' => 'metcalfe@test.com',
            'password' => 'password',
            'role' => 'client',
            'client_id' => $client->id,
        ]);
        $order = CleaningOrder::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_date' => '2026-06-12',
            'employee_name' => 'Julian',
            'department_number' => '2357',
            'status' => 'submitted',
            'subtotal_cents' => 725,
            'total_cents' => 725,
        ]);
        $order->items()->create([
            'client_category_id' => $category->id,
            'item_name_snapshot' => 'Habit',
            'unit_price_cents' => 725,
            'quantity' => 1,
            'total_cents' => 725,
        ]);

        $this->actingAs($user)
            ->put(route('portal.orders.update', $order), [
                'service_date' => '2026-06-13',
                'employee_name' => 'Julian',
                'department_number' => '478',
                'quantities' => [$category->id => 3],
                'unit_price_cents' => 1,
            ])
            ->assertRedirect(route('portal.orders.show', $order));

        $order->refresh()->load('items');
        $this->assertSame('submitted', $order->status);
        $this->assertSame('478', $order->department_number);
        $this->assertSame(2175, $order->subtotal_cents);
        $this->assertSame(725, $order->items->first()->unit_price_cents);
        $this->assertSame('3.00', $order->items->first()->quantity);
    }

    public function test_client_order_form_groups_catalog_items_and_keeps_prices_read_only(): void
    {
        $client = Client::create([
            'name' => 'Hilton Lac Leamy',
            'tax_profile' => 'qc_tps_tvq',
            'default_language' => 'fr',
        ]);
        ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Complet / Suit',
            'service_type' => 'dry_cleaning',
            'audience' => 'gentlemen',
            'sort_order' => 1,
            'default_price_cents' => 1850,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Chemisier / Blouse',
            'service_type' => 'laundry',
            'audience' => 'ladies',
            'sort_order' => 1,
            'default_price_cents' => 950,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Client Hilton',
            'email' => 'hilton@test.com',
            'password' => 'password',
            'role' => 'client',
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->get(route('portal.orders.create'))
            ->assertOk()
            ->assertSee('Nettoyage à sec / Dry Cleaning')
            ->assertSee('Messieurs / Gentlemen')
            ->assertSee('Blanchissage / Laundry')
            ->assertSee('Dames / Ladies')
            ->assertSee('18,50 $')
            ->assertSee('9,50 $')
            ->assertDontSee('name="unit_price', false);
    }

    public function test_admin_can_update_catalog_group_price_and_order(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-catalog@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        $client = Client::create([
            'name' => 'Hilton Lac Leamy',
            'tax_profile' => 'qc_tps_tvq',
            'default_language' => 'fr',
        ]);
        $category = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Complet / Suit',
            'service_type' => 'dry_cleaning',
            'audience' => 'gentlemen',
            'sort_order' => 8,
            'default_price_cents' => 1850,
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('clients.categories.update', [$client, $category]), [
                'name' => 'Complet 2 pièces / Suit 2 pcs',
                'service_type' => 'dry_cleaning',
                'audience' => 'gentlemen',
                'default_price' => '19,50',
                'sort_order' => 1,
                'is_taxable' => 1,
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_categories', [
            'id' => $category->id,
            'name' => 'Complet 2 pièces / Suit 2 pcs',
            'service_type' => 'dry_cleaning',
            'audience' => 'gentlemen',
            'default_price_cents' => 1950,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_client_update_without_catalog_fields_does_not_deactivate_existing_items(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-client-update@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        $client = Client::create([
            'name' => 'Holiday Inn',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
            'invoice_style' => 'hotel',
        ]);
        $category = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Chemise / Shirt',
            'service_type' => 'laundry',
            'audience' => 'gentlemen',
            'default_price_cents' => 890,
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('clients.update', $client), [
                'name' => 'Holiday Inn',
                'tax_profile' => 'on_hst',
                'default_language' => 'fr',
                'invoice_style' => 'hotel',
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_categories', [
            'id' => $category->id,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_reactivate_all_catalog_items(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-reactivate@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        $client = Client::create([
            'name' => 'Holiday Inn',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $category = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Chemise / Shirt',
            'service_type' => 'laundry',
            'audience' => 'gentlemen',
            'default_price_cents' => 890,
            'is_taxable' => true,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('clients.categories.activate-all', $client))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_categories', [
            'id' => $category->id,
            'is_active' => true,
        ]);
    }

    public function test_client_cannot_correct_an_order_after_it_is_approved(): void
    {
        $client = Client::create([
            'name' => 'Hotel Metcalfe',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $user = User::create([
            'name' => 'Client Metcalfe',
            'email' => 'metcalfe@test.com',
            'password' => 'password',
            'role' => 'client',
            'client_id' => $client->id,
        ]);
        $order = CleaningOrder::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'service_date' => '2026-06-12',
            'employee_name' => 'Julian',
            'department_number' => '2357',
            'status' => 'reviewed',
        ]);

        $this->actingAs($user)
            ->get(route('portal.orders.edit', $order))
            ->assertForbidden();
    }

    public function test_admin_can_approve_a_client_order_and_create_its_invoice(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        $client = Client::create([
            'name' => 'Hotel Metcalfe',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $category = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Habit',
            'default_price_cents' => 725,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $order = CleaningOrder::create([
            'client_id' => $client->id,
            'service_date' => '2026-06-12',
            'employee_name' => 'Julian',
            'department_number' => '2357',
            'status' => 'submitted',
            'subtotal_cents' => 5075,
            'total_cents' => 5075,
        ]);
        $order->items()->create([
            'client_category_id' => $category->id,
            'item_name_snapshot' => 'Habit',
            'unit_price_cents' => 725,
            'quantity' => 7,
            'total_cents' => 5075,
        ]);

        $this->actingAs($admin)
            ->post(route('cleaning-orders.approve', $order))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cleaning_orders', [
            'id' => $order->id,
            'status' => 'reviewed',
            'department_number' => '2357',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('cleaning-orders.create-invoice', $order));

        $invoice = MonthlyInvoice::with('entries')->firstOrFail();
        $response->assertRedirect(route('monthly-invoices.show', $invoice));

        $order->refresh();
        $this->assertSame('invoiced', $order->status);
        $this->assertSame($invoice->id, $order->monthly_invoice_id);
        $this->assertSame(5075, $invoice->subtotal_cents);
        $this->assertSame(660, $invoice->tax_cents);
        $this->assertSame(5735, $invoice->grand_total_cents);
        $this->assertStringContainsString('No de département: 2357', $invoice->notes);

        $entry = $invoice->entries->firstOrFail();
        $this->assertSame(5075, $entry->amount_cents);
        $this->assertSame('Habit', $entry->category_name_snapshot);
        $this->assertSame('7', $entry->item_details[0]['quantity']);
        $this->assertSame(725, $entry->item_details[0]['unit_price_cents']);
    }

    public function test_admin_can_create_one_monthly_invoice_from_approved_account_statement_orders(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        $client = Client::create([
            'name' => 'Hotel Metcalfe',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $category = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Habit',
            'default_price_cents' => 725,
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $firstOrder = CleaningOrder::create([
            'client_id' => $client->id,
            'service_date' => '2026-06-12',
            'employee_name' => 'Julian',
            'department_number' => '2357',
            'status' => 'reviewed',
            'subtotal_cents' => 5075,
            'total_cents' => 5075,
        ]);
        $firstOrder->items()->create([
            'client_category_id' => $category->id,
            'item_name_snapshot' => 'Habit',
            'unit_price_cents' => 725,
            'quantity' => 7,
            'total_cents' => 5075,
        ]);

        $secondOrder = CleaningOrder::create([
            'client_id' => $client->id,
            'service_date' => '2026-06-13',
            'employee_name' => 'Marie',
            'department_number' => '478',
            'status' => 'reviewed',
            'subtotal_cents' => 1450,
            'total_cents' => 1450,
        ]);
        $secondOrder->items()->create([
            'client_category_id' => $category->id,
            'item_name_snapshot' => 'Habit',
            'unit_price_cents' => 725,
            'quantity' => 2,
            'total_cents' => 1450,
        ]);

        $pendingOrder = CleaningOrder::create([
            'client_id' => $client->id,
            'service_date' => '2026-06-14',
            'employee_name' => 'Pas approuvé',
            'department_number' => '999',
            'status' => 'submitted',
            'subtotal_cents' => 725,
            'total_cents' => 725,
        ]);
        $pendingOrder->items()->create([
            'client_category_id' => $category->id,
            'item_name_snapshot' => 'Habit',
            'unit_price_cents' => 725,
            'quantity' => 1,
            'total_cents' => 725,
        ]);

        $response = $this->actingAs($admin)->post(route('account-statements.create-invoice'), [
            'month' => 6,
            'year' => 2026,
            'client_id' => $client->id,
        ]);

        $invoice = MonthlyInvoice::with('entries')->firstOrFail();
        $response->assertRedirect(route('monthly-invoices.show', $invoice));

        $this->assertSame(6525, $invoice->subtotal_cents);
        $this->assertSame(848, $invoice->tax_cents);
        $this->assertSame(7373, $invoice->grand_total_cents);
        $this->assertCount(2, $invoice->entries);
        $this->assertDatabaseHas('cleaning_orders', ['id' => $firstOrder->id, 'status' => 'invoiced', 'monthly_invoice_id' => $invoice->id]);
        $this->assertDatabaseHas('cleaning_orders', ['id' => $secondOrder->id, 'status' => 'invoiced', 'monthly_invoice_id' => $invoice->id]);
        $this->assertDatabaseHas('cleaning_orders', ['id' => $pendingOrder->id, 'status' => 'submitted', 'monthly_invoice_id' => null]);
    }
}
