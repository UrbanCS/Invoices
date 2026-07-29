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
