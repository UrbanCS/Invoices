<?php

namespace Tests\Feature;

use App\Models\CleaningOrder;
use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\MonthlyInvoice;
use App\Models\User;
use App\Services\InvoicePresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleaningOrderInvoiceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_delete_an_unbilled_cleaning_order(): void
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
            'audience' => 'employees',
            'default_price_cents' => 725,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $order = CleaningOrder::create([
            'client_id' => $client->id,
            'service_date' => '2026-06-12',
            'employee_name' => 'Julian',
            'status' => 'reviewed',
            'subtotal_cents' => 725,
            'total_cents' => 725,
        ]);
        $item = $order->items()->create([
            'client_category_id' => $category->id,
            'item_name_snapshot' => 'Habit',
            'unit_price_cents' => 725,
            'quantity' => 1,
            'total_cents' => 725,
        ]);

        $this->actingAs($admin)
            ->delete(route('cleaning-orders.destroy', $order))
            ->assertRedirect()
            ->assertSessionHas('status', 'Commande supprimée définitivement.');

        $this->assertDatabaseMissing('cleaning_orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('cleaning_order_items', ['id' => $item->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cleaning_order.deleted',
            'entity_id' => $order->id,
        ]);
    }

    public function test_employee_cannot_delete_a_cleaning_order(): void
    {
        $employee = User::create([
            'name' => 'Employé',
            'email' => 'employee@test.com',
            'password' => 'password',
            'role' => 'employee',
        ]);
        $client = Client::create([
            'name' => 'Hotel Metcalfe',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $order = CleaningOrder::create([
            'client_id' => $client->id,
            'service_date' => '2026-06-12',
            'employee_name' => 'Julian',
            'status' => 'submitted',
            'subtotal_cents' => 725,
            'total_cents' => 725,
        ]);

        $this->actingAs($employee)
            ->delete(route('cleaning-orders.destroy', $order))
            ->assertForbidden();

        $this->assertDatabaseHas('cleaning_orders', ['id' => $order->id]);
    }

    public function test_super_admin_cannot_delete_an_invoiced_cleaning_order(): void
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
        $invoice = MonthlyInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'TEST-001',
            'invoice_month' => 6,
            'invoice_year' => 2026,
            'invoice_date' => '2026-06-30',
            'status' => 'approved',
            'source_mode' => 'manual_grid',
            'created_by' => $admin->id,
        ]);
        $order = CleaningOrder::create([
            'client_id' => $client->id,
            'monthly_invoice_id' => $invoice->id,
            'service_date' => '2026-06-12',
            'employee_name' => 'Julian',
            'status' => 'invoiced',
            'subtotal_cents' => 725,
            'total_cents' => 725,
        ]);

        $this->actingAs($admin)
            ->from(route('monthly-invoices.index'))
            ->delete(route('cleaning-orders.destroy', $order))
            ->assertRedirect(route('monthly-invoices.index'))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('cleaning_orders', ['id' => $order->id]);
    }

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
            'audience' => 'employees',
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
            'order_type' => 'employee',
            'employee_name' => 'Julian',
            'employee_tag_number' => '0096',
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
                'order_type' => 'employee',
                'employee_name' => 'Julian',
                'employee_tag_number' => '0097',
                'department_number' => '478',
                'quantities' => [$category->id => 3],
                'unit_price_cents' => 1,
            ])
            ->assertRedirect(route('portal.orders.show', $order));

        $order->refresh()->load('items');
        $this->assertSame('submitted', $order->status);
        $this->assertSame('employee', $order->order_type);
        $this->assertSame('0097', $order->employee_tag_number);
        $this->assertSame('478', $order->department_number);
        $this->assertSame(2175, $order->subtotal_cents);
        $this->assertSame(725, $order->items->first()->unit_price_cents);
        $this->assertSame('3.00', $order->items->first()->quantity);
    }

    public function test_hotel_client_can_submit_employee_and_guest_orders_with_the_correct_fixed_prices(): void
    {
        $client = Client::create([
            'name' => 'Best Western',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $employeeCategory = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Pantalon employé',
            'audience' => 'employees',
            'default_price_cents' => 500,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $guestCategory = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Pantalon client',
            'audience' => 'gentlemen',
            'default_price_cents' => 1150,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Client Best Western',
            'email' => 'best-western@test.com',
            'password' => 'password',
            'role' => 'client',
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->post(route('portal.orders.store'), [
                'service_date' => '2026-06-03',
                'order_type' => 'employee',
                'new_employee_name' => 'Julian',
                'employee_tag_number' => 'ET-42',
                'department_number' => 'Entretien',
                'quantities' => [$employeeCategory->id => 2],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('portal.orders.store'), [
                'service_date' => '2026-06-04',
                'order_type' => 'hotel_guest',
                'guest_name' => 'Alex Martin',
                'guest_tag_number' => 'GT-77',
                'room_number' => '478',
                'quantities' => [$guestCategory->id => 2],
            ])
            ->assertSessionHasNoErrors();

        $employeeOrder = CleaningOrder::where('order_type', 'employee')->firstOrFail();
        $guestOrder = CleaningOrder::where('order_type', 'hotel_guest')->firstOrFail();

        $this->assertSame('Julian', $employeeOrder->employee_name);
        $this->assertSame('ET-42', $employeeOrder->employee_tag_number);
        $this->assertSame(1000, $employeeOrder->total_cents);
        $this->assertSame(500, $employeeOrder->items()->firstOrFail()->unit_price_cents);
        $this->assertSame('Alex Martin', $guestOrder->guest_name);
        $this->assertSame('GT-77', $guestOrder->guest_tag_number);
        $this->assertSame('478', $guestOrder->room_number);
        $this->assertSame(2300, $guestOrder->total_cents);
        $this->assertSame(1150, $guestOrder->items()->firstOrFail()->unit_price_cents);
    }

    public function test_hotel_guest_order_requires_a_tag_number(): void
    {
        $client = Client::create([
            'name' => 'Best Western',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $guestCategory = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Pantalon client',
            'audience' => 'gentlemen',
            'default_price_cents' => 1150,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Client Best Western',
            'email' => 'best-western-tag-required@test.com',
            'password' => 'password',
            'role' => 'client',
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->post(route('portal.orders.store'), [
                'service_date' => '2026-06-04',
                'order_type' => 'hotel_guest',
                'guest_name' => 'Alex Martin',
                'room_number' => '478',
                'quantities' => [$guestCategory->id => 2],
            ])
            ->assertSessionHasErrors('guest_tag_number');

        $this->assertDatabaseCount('cleaning_orders', 0);
    }

    public function test_hotel_client_cannot_use_a_guest_price_for_an_employee_order(): void
    {
        $client = Client::create([
            'name' => 'Best Western',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $guestCategory = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Manteau client',
            'audience' => 'ladies',
            'default_price_cents' => 2500,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $user = User::create([
            'name' => 'Client Best Western',
            'email' => 'best-western-reject@test.com',
            'password' => 'password',
            'role' => 'client',
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->from(route('portal.orders.create'))
            ->post(route('portal.orders.store'), [
                'service_date' => '2026-06-03',
                'order_type' => 'employee',
                'new_employee_name' => 'Julian',
                'employee_tag_number' => 'ET-42',
                'department_number' => 'Entretien',
                'quantities' => [$guestCategory->id => 1],
            ])
            ->assertRedirect(route('portal.orders.create'))
            ->assertSessionHasErrors('quantities');

        $this->assertDatabaseCount('cleaning_orders', 0);
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

    public function test_invoice_form_loads_items_for_the_explicitly_selected_client(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-invoice-catalog@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        Client::create([
            'name' => 'Client sans catalogue',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $holidayInn = Client::create([
            'name' => 'Holiday Inn Ottawa Downtown',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        ClientCategory::create([
            'client_id' => $holidayInn->id,
            'name' => 'Chemise / Shirt',
            'service_type' => 'laundry',
            'audience' => 'gentlemen',
            'default_price_cents' => 890,
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('monthly-invoices.create', ['client_id' => $holidayInn->id]))
            ->assertOk()
            ->assertSee('data-rendered-client-id="'.$holidayInn->id.'"', false)
            ->assertSee('Catalogue chargé : 1 item(s) actif(s).')
            ->assertSee('Chemise / Shirt')
            ->assertDontSee('Le client sélectionné n’a aucun item enregistré.');
    }

    public function test_invoice_form_collapses_a_large_catalog_into_an_item_summary(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-large-catalog@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        $client = Client::create([
            'name' => 'Holiday Inn Ottawa Downtown',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);

        foreach (range(1, 9) as $position) {
            ClientCategory::create([
                'client_id' => $client->id,
                'name' => 'Item '.$position,
                'service_type' => 'dry_cleaning',
                'audience' => 'unisex',
                'sort_order' => $position,
                'default_price_cents' => 500 + $position,
                'is_taxable' => true,
                'is_active' => true,
            ]);
        }

        $this->actingAs($admin)
            ->get(route('monthly-invoices.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('Items ajoutés à la facture')
            ->assertSee('Aucun item ajouté pour l’instant.')
            ->assertSee('Le catalogue contient 9 items.');
    }

    public function test_saved_invoice_uses_a_readable_summary_for_a_large_catalog(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-saved-invoice-summary@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        $client = Client::create([
            'name' => 'Holiday Inn Ottawa Downtown',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $categories = collect(range(1, 9))->map(fn ($position) => [
            'id' => $position,
            'name' => 'Item '.$position,
            'service_type' => 'dry_cleaning',
            'audience' => 'unisex',
            'is_taxable' => true,
        ])->all();
        $invoice = MonthlyInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'TEST-SUMMARY-0726',
            'invoice_month' => 7,
            'invoice_year' => 2026,
            'invoice_date' => '2026-07-28',
            'status' => 'draft',
            'source_mode' => 'manual_grid',
            'subtotal_cents' => 2590,
            'grand_total_cents' => 2590,
            'category_snapshot' => $categories,
            'created_by' => $admin->id,
        ]);
        $invoice->entries()->create([
            'service_day' => 1,
            'client_category_id' => null,
            'category_name_snapshot' => 'Item 1',
            'amount_cents' => 2590,
            'item_details' => [[
                'label' => 'Suit 2 pc / Complet 2 pc',
                'quantity' => 2,
                'unit_price_cents' => 1295,
                'total_cents' => 2590,
            ]],
            'source_type' => 'manual_monthly_grid',
        ]);

        $this->actingAs($admin)
            ->get(route('monthly-invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Détail des items facturés')
            ->assertSee('Suit 2 pc / Complet 2 pc')
            ->assertSee('12,95 $')
            ->assertSee('25,90 $')
            ->assertSee('Afficher la grille technique par item');
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
        $this->assertSame('approved', $invoice->status);
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

        $this->assertSame('approved', $invoice->status);
        $this->assertSame(6525, $invoice->subtotal_cents);
        $this->assertSame(848, $invoice->tax_cents);
        $this->assertSame(7373, $invoice->grand_total_cents);
        $this->assertCount(2, $invoice->entries);
        $this->assertDatabaseHas('cleaning_orders', ['id' => $firstOrder->id, 'status' => 'invoiced', 'monthly_invoice_id' => $invoice->id]);
        $this->assertDatabaseHas('cleaning_orders', ['id' => $secondOrder->id, 'status' => 'invoiced', 'monthly_invoice_id' => $invoice->id]);
        $this->assertDatabaseHas('cleaning_orders', ['id' => $pendingOrder->id, 'status' => 'submitted', 'monthly_invoice_id' => null]);
    }

    public function test_monthly_invoice_from_orders_separates_employee_and_hotel_guest_details(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-hotel-billing@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        $client = Client::create([
            'name' => 'Best Western',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $employeeCategory = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Pantalon employé',
            'audience' => 'employees',
            'default_price_cents' => 500,
            'is_taxable' => true,
            'is_active' => true,
        ]);
        $guestCategory = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Pantalon client',
            'audience' => 'gentlemen',
            'default_price_cents' => 1150,
            'is_taxable' => true,
            'is_active' => true,
        ]);

        $employeeOrder = CleaningOrder::create([
            'client_id' => $client->id,
            'service_date' => '2026-06-03',
            'order_type' => 'employee',
            'employee_name' => 'Julian',
            'employee_tag_number' => 'ET-42',
            'department_number' => 'Entretien',
            'status' => 'reviewed',
            'subtotal_cents' => 1000,
            'total_cents' => 1000,
        ]);
        $employeeOrder->items()->create([
            'client_category_id' => $employeeCategory->id,
            'item_name_snapshot' => 'Pantalon employé',
            'unit_price_cents' => 500,
            'quantity' => 2,
            'total_cents' => 1000,
        ]);

        $guestOrder = CleaningOrder::create([
            'client_id' => $client->id,
            'service_date' => '2026-06-04',
            'order_type' => 'hotel_guest',
            'guest_name' => 'Alex Martin',
            'guest_tag_number' => 'GT-77',
            'room_number' => '478',
            'status' => 'reviewed',
            'subtotal_cents' => 1150,
            'total_cents' => 1150,
        ]);
        $guestOrder->items()->create([
            'client_category_id' => $guestCategory->id,
            'item_name_snapshot' => 'Pantalon client',
            'unit_price_cents' => 1150,
            'quantity' => 1,
            'total_cents' => 1150,
        ]);

        $response = $this->actingAs($admin)->post(route('account-statements.create-invoice'), [
            'month' => 6,
            'year' => 2026,
            'client_id' => $client->id,
        ]);

        $invoice = MonthlyInvoice::with(['entries', 'client'])->firstOrFail();
        $response->assertRedirect(route('monthly-invoices.show', $invoice));

        $presentation = app(InvoicePresentationService::class);
        $lineItems = $presentation->lineItems($invoice);
        $dailyTotals = $presentation->dailyBillingTotals($lineItems);

        $this->assertSame([
            'employee' => 1000,
            'hotel_guest' => 1150,
        ], $presentation->billingSubtotals($lineItems));
        $this->assertSame(1000, $dailyTotals->get(3)['employee']);
        $this->assertSame(1150, $dailyTotals->get(4)['hotel_guest']);
        $this->assertSame('Julian', $lineItems->firstWhere('billing_type', 'employee')['person_name']);
        $this->assertSame('ET-42', $lineItems->firstWhere('billing_type', 'employee')['reference_number']);
        $this->assertSame('Alex Martin', $lineItems->firstWhere('billing_type', 'hotel_guest')['person_name']);
        $this->assertSame('GT-77', $lineItems->firstWhere('billing_type', 'hotel_guest')['reference_number']);
        $this->assertSame('478', $lineItems->firstWhere('billing_type', 'hotel_guest')['room_number']);

        $this->actingAs($admin)
            ->get(route('monthly-invoices.show', $invoice))
            ->assertOk()
            ->assertSee('EMPLOYÉS')
            ->assertSee('CLIENTS')
            ->assertSee('Julian')
            ->assertSee('ET-42')
            ->assertSee('Alex Martin')
            ->assertSee('GT-77')
            ->assertSee('478');
    }
}
