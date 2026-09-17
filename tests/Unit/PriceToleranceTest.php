<?php

namespace Tests\Unit;

use App\Services\Matching\PriceTolerance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PriceToleranceTest extends TestCase
{
    #[DataProvider('priceToleranceProvider')]
    public function test_matches(float $poUnitPrice, float $invoiceUnitPrice, bool $expected): void
    {
        $priceTolerance = new PriceTolerance;
        $this->assertSame($expected, $priceTolerance->matches($poUnitPrice, $invoiceUnitPrice));
    }

    public static function priceToleranceProvider(): array
    {
        return [
            [88960, 88960, true],
            [88960, 89500, true],
            [88960, 90000, false],
            [1000, 1015, true],
            [1000, 1030, false],
            [50, 52, true],
            [50, 56, false],
            [0, 0, true],
            [5000, 5100, true],
            [5000, 5200, false],
            [100, 103, false],
            [100, 102, true],
        ];
    }
}
