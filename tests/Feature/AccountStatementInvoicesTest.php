<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MonthlyInvoice;
use App\Models\User;
use App\Services\AccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatementInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(Client $client, string $number, array $attributes = []): MonthlyInvoice
    {
        return MonthlyInvoice::create(array_merge([
            'client_id' => $client->id,
            'invoice_number' => $number,
            'invoice_month' => 9,
            'invoice_year' => 2026,
            'invoice_date' => '2026-09-07',
            'status' => 'approved',
            'source_mode' => 'manual_grid',
            'subtotal_cents' => 5000,
            'total_cents' => 5000,
            'tax_cents' => 650,
            'grand_total_cents' => 5650,
            'created_by' => User::first()?->id ?? $this->manager()->id,
        ], $attributes));
    }

    private function manager(string $role = 'super_admin'): User
    {
        return User::create(['name' => 'Test', 'email' => uniqid('test-').'@example.test', 'password' => 'password', 'role' => $role]);
    }

    public function test_daily_invoices_appear_even_without_cleaning_orders_and_totals_are_not_recalculated(): void
    {
        $client = Client::create(['name' => 'Arc Test', 'tax_profile' => 'qc_tps_tvq', 'default_language' => 'fr']);
        $this->invoice($client, '0926-2');
        $this->invoice($client, '0926-3', ['total_cents' => 10500, 'tax_cents' => 1365, 'grand_total_cents' => 11865]);
        $this->invoice($client, '0926-4', ['total_cents' => 17500, 'tax_cents' => 2275, 'grand_total_cents' => 19775]);
        $parameters = ['client_id' => $client->id, 'month' => 9, 'year' => 2026];

        $this->actingAs($this->manager())->get(route('account-statements.index', $parameters))
            ->assertOk()->assertSee('0926-2')->assertSee('0926-3')->assertSee('0926-4')
            ->assertSee('372,90 $')->assertSee('Télécharger le récapitulatif du mois (PDF)');
        $summary = app(AccountStatementService::class)->forPeriod(9, 2026, $client->id);
        $this->assertSame(33000, $summary['net_cents']);
        $this->assertSame(4290, $summary['tax_cents']);
        $this->assertSame(37290, $summary['total_cents']);
        $this->assertDatabaseCount('cleaning_orders', 0);
    }

    public function test_summary_is_scoped_by_client_billing_period_and_issued_status(): void
    {
        $client = Client::create(['name' => 'Hotel A']);
        $other = Client::create(['name' => 'Hotel B']);
        $this->invoice($client, 'INCLUDED', ['invoice_date' => '2026-10-01']);
        $this->invoice($other, 'OTHER-HOTEL');
        $this->invoice($client, 'OTHER-MONTH', ['invoice_month' => 8]);
        $this->invoice($client, 'OTHER-YEAR', ['invoice_year' => 2025]);
        $this->invoice($client, 'DRAFT', ['status' => 'draft']);
        $this->invoice($client, 'CANCELLED', ['status' => 'cancelled']);
        $this->actingAs($this->manager('employee'))
            ->get(route('account-statements.summary', ['month' => 9, 'year' => 2026, 'client_id' => $client->id]))
            ->assertOk()->assertSee('INCLUDED')->assertDontSee('OTHER-HOTEL')
            ->assertDontSee('OTHER-MONTH')->assertDontSee('OTHER-YEAR')->assertDontSee('DRAFT')->assertDontSee('CANCELLED');
    }

    public function test_payments_and_paid_invoices_are_reflected_in_the_balance(): void
    {
        $client = Client::create(['name' => 'Hotel']);
        $partial = $this->invoice($client, 'PARTIAL', ['status' => 'sent']);
        $partial->payments()->create(['amount_cents' => 1000, 'paid_at' => now(), 'method' => 'manual']);
        $paid = $this->invoice($client, 'PAID', ['status' => 'paid']);
        $paid->payments()->create(['amount_cents' => 5650, 'paid_at' => now(), 'method' => 'manual']);
        $summary = app(AccountStatementService::class)->forPeriod(9, 2026, $client->id);
        $this->assertSame(11300, $summary['total_cents']);
        $this->assertSame(6650, $summary['paid_cents']);
        $this->assertSame(4650, $summary['balance_cents']);
    }

    public function test_repeated_pdf_generation_does_not_create_or_change_invoices_and_respects_language(): void
    {
        $client = Client::create(['name' => 'Hotel English', 'default_language' => 'en']);
        $invoice = $this->invoice($client, 'EN-001');
        $before = $invoice->fresh()->getAttributes();
        $parameters = ['client_id' => $client->id, 'month' => 9, 'year' => 2026];
        $this->actingAs($this->manager())->get(route('account-statements.summary', $parameters))
            ->assertOk()->assertSee('Monthly account statement')->assertSee('$56.50')->assertSee('EN-001');
        foreach ([1, 2] as $attempt) {
            $response = $this->get(route('account-statements.summary', [...$parameters, 'pdf' => 1]));
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }
        $this->assertDatabaseCount('monthly_invoices', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($before, $invoice->fresh()->getAttributes());
    }

    public function test_client_users_and_guests_cannot_access_admin_statements(): void
    {
        $client = Client::create(['name' => 'Hotel']);
        $this->invoice($client, 'PRIVATE');
        $parameters = ['month' => 9, 'year' => 2026, 'client_id' => $client->id, 'pdf' => 1];
        $this->get(route('account-statements.summary', $parameters))->assertRedirect(route('login'));
        $user = $this->manager('client');
        $user->update(['client_id' => $client->id]);
        $this->actingAs($user)->get(route('account-statements.summary', $parameters))->assertForbidden();
        $this->get(route('account-statements.index', $parameters))->assertForbidden();
    }

    public function test_invalid_filters_and_empty_period_do_not_produce_a_statement(): void
    {
        $client = Client::create(['name' => 'Hotel']);
        $this->actingAs($this->manager())->get(route('account-statements.index', ['month' => 13]))
            ->assertSessionHasErrors('month');
        $this->get(route('account-statements.summary', ['month' => 9, 'year' => 2026]))
            ->assertSessionHasErrors('client_id');
        $this->get(route('account-statements.summary', ['month' => 9, 'year' => 2026, 'client_id' => $client->id, 'pdf' => 1]))
            ->assertRedirect()->assertSessionHasErrors();
        $this->assertDatabaseCount('monthly_invoices', 0);
    }
}
