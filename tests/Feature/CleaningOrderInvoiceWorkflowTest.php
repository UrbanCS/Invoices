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
}
