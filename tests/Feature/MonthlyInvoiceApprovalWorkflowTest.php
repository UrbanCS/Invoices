<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\MonthlyInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyInvoiceApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_creates_an_automatically_approved_invoice(): void
    {
        [$user, $client, $category] = $this->invoiceContext('super_admin');

        $this->actingAs($user)
            ->get(route('monthly-invoices.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('Créer la facture')
            ->assertSee('La facture sera approuvée automatiquement.');

        $response = $this->actingAs($user)->post(route('monthly-invoices.store'), [
            'client_id' => $client->id,
            'invoice_number' => 'AUTO-ADMIN-0726',
            'invoice_month' => 7,
            'invoice_year' => 2026,
            'invoice_date' => '2026-07-28',
            'source_mode' => 'manual_grid',
            'grid' => [1 => [$category->id => '50,00']],
        ]);

        $invoice = MonthlyInvoice::firstOrFail();

        $response
            ->assertRedirect(route('monthly-invoices.show', $invoice))
            ->assertSessionHas('status', 'Facture créée et approuvée automatiquement.');
        $this->assertSame('approved', $invoice->status);
    }

    public function test_employee_still_creates_a_draft_invoice(): void
    {
        [$user, $client, $category] = $this->invoiceContext('employee');

        $this->actingAs($user)
            ->get(route('monthly-invoices.create', ['client_id' => $client->id]))
            ->assertOk()
            ->assertSee('Sauvegarder brouillon')
            ->assertDontSee('La facture sera approuvée automatiquement.');

        $response = $this->actingAs($user)->post(route('monthly-invoices.store'), [
            'client_id' => $client->id,
            'invoice_number' => 'EMPLOYEE-DRAFT-0726',
            'invoice_month' => 7,
            'invoice_year' => 2026,
            'invoice_date' => '2026-07-28',
            'source_mode' => 'manual_grid',
            'grid' => [1 => [$category->id => '50,00']],
        ]);

        $invoice = MonthlyInvoice::firstOrFail();

        $response
            ->assertRedirect(route('monthly-invoices.show', $invoice))
            ->assertSessionHas('status', 'Brouillon de facture créé.');
        $this->assertSame('draft', $invoice->status);
    }

    public function test_employee_cannot_approve_an_invoice(): void
    {
        [$user, $client] = $this->invoiceContext('employee');
        $invoice = MonthlyInvoice::create([
            'client_id' => $client->id,
            'invoice_number' => 'NO-EMPLOYEE-APPROVAL',
            'invoice_month' => 7,
            'invoice_year' => 2026,
            'invoice_date' => '2026-07-28',
            'status' => 'draft',
            'source_mode' => 'manual_grid',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('monthly-invoices.approve', $invoice))
            ->assertForbidden();

        $this->assertSame('draft', $invoice->fresh()->status);
    }

    public function test_manual_hotel_invoice_preserves_employee_and_guest_identity_details(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-hotel-details@test.com',
            'password' => 'password',
            'role' => 'super_admin',
        ]);
        $client = Client::create([
            'name' => 'Best Western',
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
            'invoice_style' => 'hotel',
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

        $response = $this->actingAs($admin)->post(route('monthly-invoices.store'), [
            'client_id' => $client->id,
            'invoice_number' => 'BW-EMPLOYEE-GUEST-0626',
            'invoice_month' => 6,
            'invoice_year' => 2026,
            'invoice_date' => '2026-06-30',
            'source_mode' => 'manual_grid',
            'grid' => [
                3 => [$employeeCategory->id => '10,00'],
                4 => [$guestCategory->id => '11,50'],
            ],
            'details' => [
                3 => [
                    $employeeCategory->id => [[
                        'label' => 'Pantalon employé',
                        'quantity' => '2',
                        'unit_price' => '5,00',
                        'billing_type' => 'employee',
                        'person_name' => 'Julian',
                        'reference_number' => 'ET-42',
                        'department_number' => 'Entretien',
                    ]],
                ],
                4 => [
                    $guestCategory->id => [[
                        'label' => 'Pantalon client',
                        'quantity' => '1',
                        'unit_price' => '11,50',
                        'billing_type' => 'hotel_guest',
                        'person_name' => 'Alex Martin',
                        'reference_number' => '478',
                    ]],
                ],
            ],
        ]);

        $invoice = MonthlyInvoice::with('entries')->firstOrFail();

        $response->assertRedirect(route('monthly-invoices.show', $invoice));
        $this->assertSame('approved', $invoice->status);
        $this->assertSame(2150, $invoice->subtotal_cents);

        $employeeDetail = $invoice->entries
            ->firstWhere('client_category_id', $employeeCategory->id)
            ->item_details[0];
        $guestDetail = $invoice->entries
            ->firstWhere('client_category_id', $guestCategory->id)
            ->item_details[0];

        $this->assertSame('employee', $employeeDetail['billing_type']);
        $this->assertSame('Julian', $employeeDetail['person_name']);
        $this->assertSame('ET-42', $employeeDetail['reference_number']);
        $this->assertSame('Entretien', $employeeDetail['department_number']);
        $this->assertSame('hotel_guest', $guestDetail['billing_type']);
        $this->assertSame('Alex Martin', $guestDetail['person_name']);
        $this->assertSame('478', $guestDetail['reference_number']);

        $this->actingAs($admin)
            ->get(route('monthly-invoices.show', $invoice))
            ->assertOk()
            ->assertSee('EMPLOYÉS')
            ->assertSee('CLIENTS')
            ->assertSee('Julian')
            ->assertSee('ET-42')
            ->assertSee('Alex Martin')
            ->assertSee('478')
            ->assertSee('10,00 $')
            ->assertSee('11,50 $');
    }

    private function invoiceContext(string $role): array
    {
        $user = User::create([
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $role.'-invoice-approval@test.com',
            'password' => 'password',
            'role' => $role,
        ]);
        $client = Client::create([
            'name' => 'Client '.$role,
            'tax_profile' => 'on_hst',
            'default_language' => 'fr',
        ]);
        $category = ClientCategory::create([
            'client_id' => $client->id,
            'name' => 'Chemise',
            'default_price_cents' => 500,
            'is_taxable' => true,
            'is_active' => true,
        ]);

        return [$user, $client, $category];
    }
}
