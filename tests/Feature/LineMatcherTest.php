<?php

namespace Tests\Feature;

use App\Models\PoLine;
use App\Models\PurchaseOrder;
use App\Services\Extraction\InvoiceData;
use App\Services\Extraction\InvoiceLineData;
use App\Services\Matching\LineMatcher;
use App\Services\Matching\PartNumberMatcher;
use App\Services\Matching\PriceTolerance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LineMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function poWithLine(string $partNumber, float $unitPrice): PurchaseOrder
    {
        return PurchaseOrder::factory()
            ->has(PoLine::factory()->state([
                'part_number' => $partNumber,
                'unit_price' => $unitPrice,
            ]), 'lines')
            ->create();
    }

    #[DataProvider('lineCases')]
    public function test_line_confirms_a_po(
        string $poPart,
        float $poPrice,
        string $invoicePart,
        float $invoicePrice,
        bool $expected,
    ): void {
        $po = $this->poWithLine($poPart, $poPrice);

        $invoice = new InvoiceData(lines: [
            new InvoiceLineData(partNumber: $invoicePart, unitPrice: $invoicePrice),
        ]);

        $matcher = new LineMatcher(new PartNumberMatcher, new PriceTolerance);

        $this->assertSame($expected, $matcher->hasConfirmingLine($invoice, $po));
    }

    public static function lineCases(): array
    {
        return [
            ['TNE-RCTSP-MODEI-1025380', 88960.00, 'TNE-RCTSP-MODEI', 88960.00, true],
            ['TNE-RCTSP-MODEI-1025380', 88960.00, 'TNE-RCTSP-MODEI', 89400.00, true],   // 0.5%, inside 1%
            ['TNE-RCTSP-MODEI-1025380', 88960.00, 'TNE-RCTSP-MODEI', 91600.00, false],  // 3%, outside
            ['FPR1010-ASA-K9',            292.27, 'FPR1010-ASA-K9',    292.27, true],
            ['QA01833',                   292.27, 'NNTN8844B',         292.27, false],  // price alone isn't enough
        ];
    }

    /**
     * A PO with one line per [partNumber, unitPrice] pair.
     *
     * @param  list<array{0: string, 1: float}>  $lines
     */
    private function poWithLines(array $lines): PurchaseOrder
    {
        $po = PurchaseOrder::factory()->create();

        foreach ($lines as [$partNumber, $unitPrice]) {
            PoLine::factory()->for($po)->create([
                'part_number' => $partNumber,
                'unit_price' => $unitPrice,
            ]);
        }

        // Without load(), $po->lines is still the empty collection cached at create() time.
        return $po->load('lines');
    }

    /**
     * @param  list<array{0: ?string, 1: ?float}>  $lines
     */
    private function invoiceWithLines(array $lines): InvoiceData
    {
        $invoiceLines = [];

        foreach ($lines as [$partNumber, $unitPrice]) {
            $invoiceLines[] = new InvoiceLineData(partNumber: $partNumber, unitPrice: $unitPrice);
        }

        return new InvoiceData(lines: $invoiceLines);
    }

    private function matcher(): LineMatcher
    {
        return new LineMatcher(new PartNumberMatcher, new PriceTolerance);
    }

    /**
     * The PO every score test is measured against.
     */
    private function threeLinePo(): PurchaseOrder
    {
        return $this->poWithLines([
            ['AAA1111111', 100.00],
            ['BBB2222222', 200.00],
            ['CCC3333333', 300.00],
        ]);
    }

    public function test_score_is_the_proportion_of_confirming_lines(): void
    {
        $invoice = $this->invoiceWithLines([
            ['AAA1111111', 100.00],   // confirms
            ['BBB2222222', 200.00],   // confirms
            ['ZZZ9999999', 999.00],   // nothing on the PO matches
        ]);

        $this->assertEqualsWithDelta(2 / 3, $this->matcher()->score($invoice, $this->threeLinePo()), 0.001);
    }

    public function test_score_is_one_when_every_line_confirms(): void
    {
        $invoice = $this->invoiceWithLines([
            ['AAA1111111', 100.00],
            ['BBB2222222', 200.00],
            ['CCC3333333', 300.00],
        ]);

        $this->assertEqualsWithDelta(1.0, $this->matcher()->score($invoice, $this->threeLinePo()), 0.001);
    }

    public function test_score_is_zero_when_nothing_confirms(): void
    {
        $invoice = $this->invoiceWithLines([
            ['ZZZ9999999', 999.00],
            ['YYY8888888', 888.00],
        ]);

        $this->assertEqualsWithDelta(0.0, $this->matcher()->score($invoice, $this->threeLinePo()), 0.001);
    }

    /**
     * A line with no part number cannot be checked, so it leaves the denominator rather than
     * counting as a failure. One checkable line that confirms is a perfect score, not a half one.
     */
    public function test_lines_without_a_part_number_are_excluded_from_the_denominator(): void
    {
        $invoice = $this->invoiceWithLines([
            ['AAA1111111', 100.00],   // checkable, confirms
            [null, 250.00],           // not checkable
        ]);

        $this->assertEqualsWithDelta(1.0, $this->matcher()->score($invoice, $this->threeLinePo()), 0.001);
    }

    public function test_score_is_zero_when_the_invoice_has_no_lines(): void
    {
        $invoice = new InvoiceData;

        $this->assertEqualsWithDelta(0.0, $this->matcher()->score($invoice, $this->threeLinePo()), 0.001);
    }

    public function test_has_confirming_line_is_false_when_the_invoice_has_no_lines(): void
    {
        $invoice = new InvoiceData;

        $this->assertFalse($this->matcher()->hasConfirmingLine($invoice, $this->threeLinePo()));
    }
}
