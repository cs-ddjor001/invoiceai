<?php

namespace Tests\Feature;

use App\Services\Extraction\InvoiceExtractionPipeline;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceExtractionPipelineTest extends TestCase
{
    public function test_invoice_extraction_pipeline(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    ['message' => ['content' => "```json\n{\"invoice_number\": \"13983\", \"date\": \"2/8/2025\", \"total\": \"\$88,960.00\"}\n```"]],
                ],
            ]),
        ]);

        $invoiceData = (new InvoiceExtractionPipeline)->extract('tests/fixtures/pdfs/text-invoice.pdf');
        $this->assertSame('13983', $invoiceData->invoiceNumber);
        $this->assertSame('2025-02-08', $invoiceData->date);
        $this->assertSame(88960.0, $invoiceData->total);
    }
}
