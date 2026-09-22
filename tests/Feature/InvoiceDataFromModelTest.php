<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\Extraction\InvoiceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDataFromModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_invoice_data_from_a_saved_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'po_number' => '1413097',
            'issued_at' => '2025-02-08',
            'amount' => 88960.00,
        ]);

        InvoiceLine::factory()->for($invoice)->create([
            'part_number' => 'TNE-RCTSP-MODEI',
            'qty' => 1,
            'unit_price' => 88960.00,
        ]);

        $data = InvoiceData::fromModel($invoice);

        $this->assertSame('1413097', $data->poNumber);

        // The column is a date, the DTO holds a Y-m-d string.
        $this->assertSame('2025-02-08', $data->date);

        // Different names on each side: invoices.amount is InvoiceData::$total.
        $this->assertSame(88960.0, $data->total);

        $this->assertCount(1, $data->lines);
        $this->assertSame('TNE-RCTSP-MODEI', $data->lines[0]->partNumber);

        // invoice_lines.qty is InvoiceLineData::$quantity. Decimal columns come back as
        // strings, so these also check that they arrive as real floats.
        $this->assertSame(1.0, $data->lines[0]->quantity);
        $this->assertSame(88960.0, $data->lines[0]->unitPrice);
    }
}
