<?php

namespace Tests\Feature;

use App\Enums\MatchStrategy;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceMatch;
use App\Models\PoLine;
use App\Models\PurchaseOrder;
use App\Services\Matching\InvoiceMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_the_invoice_has_no_po_number(): void
    {
        $invoice = Invoice::factory()->create(['po_number' => null]);

        $result = app(InvoiceMatcher::class)->match($invoice);

        $this->assertNull($result);
        $this->assertDatabaseCount('invoice_matches', 0);
    }

    public function test_returns_null_when_no_purchase_order_has_that_number(): void
    {
        PurchaseOrder::factory()->create(['po_number' => '1413097']);
        $invoice = Invoice::factory()->create(['po_number' => '9999999']);

        $result = app(InvoiceMatcher::class)->match($invoice);

        $this->assertNull($result);
        $this->assertDatabaseCount('invoice_matches', 0);
    }

    /**
     * The simplest real match: same PO number, same line, same date. That scores the maximum,
     * 0.50 + 0.45 + 0.05 = 1.0, and it's an Exact match because a line confirms.
     */
    public function test_saves_and_returns_a_match_when_the_purchase_order_is_found(): void
    {
        $purchaseOrder = PurchaseOrder::factory()
            ->has(PoLine::factory()->state([
                'part_number' => 'AAA1111111',
                'unit_price' => 100.00,
            ]), 'lines')
            ->create(['po_number' => '1413097', 'po_date' => '2024-10-31']);

        $invoice = Invoice::factory()->create(['po_number' => '1413097', 'issued_at' => '2024-10-31']);
        InvoiceLine::factory()->for($invoice)->create([
            'part_number' => 'AAA1111111',
            'unit_price' => 100.00,
        ]);

        $result = app(InvoiceMatcher::class)->match($invoice);

        // sole() reads the table and fails unless there is exactly one row.
        $saved = InvoiceMatch::sole();

        $this->assertSame($invoice->id, $saved->invoice_id);
        $this->assertSame($purchaseOrder->id, $saved->purchase_order_id);
        $this->assertSame(MatchStrategy::Exact, $saved->strategy);
        $this->assertSame(1.0, (float) $saved->confidence);

        // is() checks that two model objects are the same database row.
        $this->assertNotNull($result);
        $this->assertTrue($result->is($saved));
    }

    /**
     * Matching will run more than once for the same invoice: the Phase 7 "re-match" button,
     * or a queue job that retries. The second run should not pile up a second row.
     */
    public function test_matching_the_same_invoice_twice_keeps_one_row(): void
    {
        PurchaseOrder::factory()->create(['po_number' => '1413097']);
        $invoice = Invoice::factory()->create(['po_number' => '1413097']);

        app(InvoiceMatcher::class)->match($invoice);
        app(InvoiceMatcher::class)->match($invoice);

        $this->assertDatabaseCount('invoice_matches', 1);
    }

    public function test_a_rematch_with_a_different_verdict_replaces_the_old_row(): void
    {
        PurchaseOrder::factory()
            ->has(PoLine::factory()->state([
                'part_number' => 'AAA1111111',
                'unit_price' => 100.00,
            ]), 'lines')
            ->create(['po_number' => '1413097']);

        $invoice = Invoice::factory()->create(['po_number' => '1413097']);
        $line = InvoiceLine::factory()->for($invoice)->create([
            'part_number' => 'ZZZ9999999',   // matches nothing on the PO
            'unit_price' => 100.00,
        ]);

        // First run: no line confirms, so the verdict is Fuzzy.
        app(InvoiceMatcher::class)->match($invoice);
        $this->assertSame(MatchStrategy::Fuzzy, InvoiceMatch::sole()->strategy);

        // A clerk corrects the part number and re-matches. fresh() reloads the invoice so
        // its lines are read again instead of reusing the ones loaded on the first run.
        $line->update(['part_number' => 'AAA1111111']);
        app(InvoiceMatcher::class)->match($invoice->fresh());

        // Second run: a line confirms, so the verdict is Exact, and it should be the only row.
        $this->assertSame(MatchStrategy::Exact, InvoiceMatch::sole()->strategy);
    }
}
