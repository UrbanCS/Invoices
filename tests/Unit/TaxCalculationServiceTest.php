<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Services\TaxCalculationService;
use PHPUnit\Framework\TestCase;

class TaxCalculationServiceTest extends TestCase
{
    public function test_ontario_hst_style_tax_is_thirteen_percent(): void
    {
        $client = new Client(['tax_profile' => 'on_hst']);
        $tax = (new TaxCalculationService())->calculate($client, 10000);

        $this->assertSame(1300, $tax['total_cents']);
    }

    public function test_quebec_tps_tvq_tax_uses_two_lines(): void
    {
        $client = new Client(['tax_profile' => 'qc_tps_tvq']);
        $tax = (new TaxCalculationService())->calculate($client, 10000);

        $this->assertSame(500, $tax['lines'][0]['amount_cents']);
        $this->assertSame(998, $tax['lines'][1]['amount_cents']);
        $this->assertSame(1498, $tax['total_cents']);
    }
}
