<?php

namespace Tests\Feature;

use App\Services\Extraction\InvoiceExtractor;
use App\Services\Extraction\LlmInvoiceExtractor;
use Tests\TestCase;

/**
 * Stands in for the Phase 5 job until that job exists.
 *
 * The point of the binding is that a caller can ask for the interface and not care which
 * class it gets. This test is that caller.
 */
class ExtractionBindingTest extends TestCase
{
    public function test_container_resolves_the_llm_driver(): void
    {
        config(['extraction.driver' => 'llm']);

        $extractor = app(InvoiceExtractor::class);

        $this->assertInstanceOf(LlmInvoiceExtractor::class, $extractor);
    }
}
