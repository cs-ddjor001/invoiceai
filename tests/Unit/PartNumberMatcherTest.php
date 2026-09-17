<?php

namespace Tests\Unit;

use App\Services\Matching\PartNumberMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PartNumberMatcherTest extends TestCase
{
    #[DataProvider('matchingPartNumbersProvider')]
    public function test_matches(?string $po, ?string $invoice, bool $expected): void
    {
        $matcher = new PartNumberMatcher;
        $this->assertSame($expected, $matcher->matches($po, $invoice));
    }

    public static function matchingPartNumbersProvider(): array
    {
        return [
            ['AF2010TS', 'AF 2010TS', true],
            ['TNE-RCTSP-MODEI-1025380', 'TNE-RCTSP-MODEI', true],
            ['4976744187001', '4976744', true],
            ['RKC05585ME', 'RKC055', false],
            ['F1VTX8602DT33X301007338', 'F1', false],
            ['SSC130124894WRK1544060', 'SSC-130-124894-WRK-1544060-EXTRA', true],
            ['QA01833', 'NNTN8844B', false],
            [null, 'ABC1234', false],
            [null, null, false],
        ];
    }
}
