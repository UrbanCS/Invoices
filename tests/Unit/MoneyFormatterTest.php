<?php

namespace Tests\Unit;

use App\Services\MoneyFormatter;
use PHPUnit\Framework\TestCase;

class MoneyFormatterTest extends TestCase
{
    public function test_it_formats_canadian_money_in_english_and_french(): void
    {
        $formatter = new MoneyFormatter();

        $this->assertSame('$1,027.50', $formatter->format(102750, 'en'));
        $this->assertSame('1 027,50 $', $formatter->format(102750, 'fr'));
    }

    public function test_it_parses_money_to_cents(): void
    {
        $formatter = new MoneyFormatter();

        $this->assertSame(102750, $formatter->parse('$1,027.50'));
        $this->assertSame(102750, $formatter->parse('1 027,50 $'));
    }
}
