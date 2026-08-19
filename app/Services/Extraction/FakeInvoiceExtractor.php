<?php

namespace App\Services\Extraction;

/**
 * Returns canned invoice data without reading the PDF or calling a model.
 *
 * Exists so the app can run with no llama-server: the test suite, a CI box, and any deploy
 * where a 4B model cannot follow. It satisfies the same interface, so nothing that consumes
 * an extractor can tell the difference.
 */
class FakeInvoiceExtractor implements InvoiceExtractor
{
    public function extract(string $path): InvoiceData
    {
        return new InvoiceData(
            invoiceNumber: '13983',
            poNumber: '4501234',
            vendorName: 'Acme Industrial Supply',
            date: '2025-03-14',
            subtotal: 1250.00,
            tax: 87.50,
            total: 1337.50,
            lines: [
                new InvoiceLineData(
                    partNumber: 'AC-1099',
                    description: 'Hex bolt, zinc plated',
                    quantity: 100.0,
                    unitPrice: 2.50,
                    amount: 250.00,
                ),
                new InvoiceLineData(
                    partNumber: 'AC-2044',
                    description: 'Lock washer',
                    quantity: 500.0,
                    unitPrice: 2.00,
                    amount: 1000.00,
                ),
            ],
        );
    }
}
