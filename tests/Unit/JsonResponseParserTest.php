<?php

namespace Tests\Unit;

use App\Services\Extraction\JsonResponseParser;
use PHPUnit\Framework\TestCase;

class JsonResponseParserTest extends TestCase
{
    public function test_fenced_json_response(): void
    {
        $parser = new JsonResponseParser;
        $parse = $parser->parse("```json\n{\"invoice_number\": \"13983\", \"po_number\": \"1413097\"}\n```");
        $this->assertSame('13983', $parse['invoice_number']);
    }

    public function test_bare_json_response(): void
    {
        $parser = new JsonResponseParser;
        $parse = $parser->parse('{"invoice_number": "13983", "po_number": "1413097"}');
        $this->assertSame('13983', $parse['invoice_number']);
    }

    public function test_braces_but_no_json_response(): void
    {
        $parser = new JsonResponseParser;
        $this->expectException(\RuntimeException::class);
        $parser->parse('{oops}');
        $this->expectExceptionMessage('unparseable');
    }

    public function test_no_braces(): void
    {
        $parser = new JsonResponseParser;
        $this->expectException(\RuntimeException::class);
        $parser->parse('I could not read that invoice.');
        $this->expectExceptionMessage('malformed');
    }
}
