<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MonthlyInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_user_cannot_view_another_clients_invoice(): void
    {
        $ownClient = Client::create(['name' => 'Own', 'tax_profile' => 'on_hst', 'default_language' => 'en']);
        $otherClient = Client::create(['name' => 'Other', 'tax_profile' => 'on_hst', 'default_language' => 'en']);
        $user = User::create(['name' => 'Client', 'email' => 'client@test.com', 'password' => 'password', 'role' => 'client', 'client_id' => $ownClient->id]);
        $invoice = MonthlyInvoice::create([
            'client_id' => $otherClient->id,
            'invoice_number' => 'T-1',
            'invoice_month' => 1,
            'invoice_year' => 2026,
            'invoice_date' => now(),
            'status' => 'sent',
            'source_mode' => 'manual_grid',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('portal.invoices.show', $invoice))->assertForbidden();
    }
}
