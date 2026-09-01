<?php

namespace Tests\Feature;

use App\Services\Extraction\LlmInvoiceExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmInvoiceExtractorTest extends TestCase
{
    public function test_llm_runs_and_responds(): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'PRETEND-MODEL-REPLY']],
                ],
            ]),
        ]);

        $extractor = new LlmInvoiceExtractor;
        $result = $extractor->extract('some invoice');

        $this->assertSame('PRETEND-MODEL-REPLY', $result);
    }
}
