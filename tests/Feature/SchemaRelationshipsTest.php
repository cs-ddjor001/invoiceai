<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\MatchStrategy;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\ExtractionRun;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceMatch;
use App\Models\PoLine;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the Phase 1 schema and models hang together: every relationship resolves, every cast
 * round-trips, and the factories can build a realistic graph in one expression.
 */
class SchemaRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invoice_owns_its_lines_and_matches(): void
    {
        // has() builds the children and wires the foreign keys. One expression replaces an
        // invoice insert, three line inserts, and the ids to connect them.
        $invoice = Invoice::factory()
            ->has(InvoiceLine::factory()->count(3), 'lines')
            ->has(ExtractionRun::factory(), 'extractionRuns')
            ->create();

        $this->assertCount(3, $invoice->lines);
        $this->assertCount(1, $invoice->extractionRuns);
        $this->assertTrue($invoice->lines->every(fn ($line) => $line->invoice_id === $invoice->id));
    }

    public function test_a_purchase_order_belongs_to_a_vendor_and_owns_its_lines(): void
    {
        $vendor = Vendor::factory()->create(['name' => 'DRAEGER INC']);

        // for() attaches an existing parent instead of creating a new one.
        $po = PurchaseOrder::factory()
            ->for($vendor)
            ->has(PoLine::factory()->count(4), 'lines')
            ->create();

        $this->assertSame('DRAEGER INC', $po->vendor->name);
        $this->assertCount(4, $po->lines);
        $this->assertSame($po->id, $po->lines->first()->purchaseOrder->id);
    }

    public function test_a_vendor_reaches_po_lines_through_its_purchase_orders(): void
    {
        $vendor = Vendor::factory()->create();

        PurchaseOrder::factory()
            ->for($vendor)
            ->has(PoLine::factory()->count(2), 'lines')
            ->count(3)
            ->create();

        // hasManyThrough: 3 POs x 2 lines, reached without ever loading the POs.
        $this->assertCount(6, $vendor->poLines);
    }

    public function test_one_invoice_can_hold_a_match_from_every_strategy(): void
    {
        $invoice = Invoice::factory()->create();
        $po = PurchaseOrder::factory()->create();

        foreach (MatchStrategy::cases() as $strategy) {
            InvoiceMatch::factory()->create([
                'invoice_id' => $invoice->id,
                'purchase_order_id' => $po->id,
                'strategy' => $strategy,
            ]);
        }

        // This is what invoice_matches replaced the source app's matched_po_id /
        // ai_matched_po_id columns to make possible.
        $this->assertCount(3, $invoice->matches);
        $this->assertEqualsCanonicalizing(
            ['exact', 'fuzzy', 'ai'],
            $invoice->matches->pluck('strategy')->map(fn ($s) => $s->value)->all(),
        );
    }

    public function test_casts_round_trip_through_the_database(): void
    {
        $match = InvoiceMatch::factory()->create([
            'strategy' => MatchStrategy::Ai,
            'confidence' => 0.8375,
            'reasoning' => ['po_score' => 0.5, 'notes' => 'Part number matched exactly.'],
            'is_accepted' => true,
        ]);

        $fresh = $match->fresh();

        $this->assertInstanceOf(MatchStrategy::class, $fresh->strategy);
        $this->assertSame(MatchStrategy::Ai, $fresh->strategy);
        $this->assertSame('0.8375', $fresh->confidence);
        $this->assertSame('Part number matched exactly.', $fresh->reasoning['notes']);
        $this->assertTrue($fresh->is_accepted);
    }

    public function test_an_activity_log_can_point_at_any_model(): void
    {
        $invoice = Invoice::factory()->create();
        $vendor = Vendor::factory()->create();

        $onInvoice = ActivityLog::factory()->for($invoice, 'subject')->create();
        $onVendor = ActivityLog::factory()->for($vendor, 'subject')->create();

        // Same table, same column, two different target models — that is the morph.
        $this->assertInstanceOf(Invoice::class, $onInvoice->subject);
        $this->assertInstanceOf(Vendor::class, $onVendor->subject);
        $this->assertSame($invoice->id, $onInvoice->subject->id);
    }

    public function test_factory_states_produce_the_scenarios_they_name(): void
    {
        $this->assertSame(Role::Admin, User::factory()->admin()->create()->role);
        $this->assertFalse(User::factory()->inactive()->create()->is_active);

        $failed = ExtractionRun::factory()->failed()->create();
        $this->assertNull($failed->raw_response);
        $this->assertNotNull($failed->error);

        $bad = Invoice::factory()->unextractable()->create();
        $this->assertSame(InvoiceStatus::Failed, $bad->status);
        $this->assertNull($bad->po_number);

        $this->assertGreaterThan(5000, (float) PoLine::factory()->highValue()->create()->unit_price);
    }

    public function test_deleting_a_vendor_keeps_its_invoices_but_drops_its_emails(): void
    {
        $vendor = Vendor::factory()
            ->has(VendorEmail::factory()->count(2), 'emails')
            ->has(Invoice::factory()->count(2), 'invoices')
            ->create();

        $vendor->delete();

        // vendor_emails cascades; invoices are financial records and only lose the link.
        $this->assertSame(0, VendorEmail::all()->count());
        $this->assertSame(2, Invoice::all()->count());
        $this->assertNull(Invoice::query()->value('vendor_id'));
    }
}
