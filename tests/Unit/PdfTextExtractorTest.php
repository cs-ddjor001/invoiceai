<?php

namespace Tests\Unit;

use App\Services\Extraction\PdfTextExtractor;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

class PdfTextExtractorTest extends TestCase
{
    private $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser;
    }

    private function fixture(string $name): string
    {
        return dirname(__DIR__).'/fixtures/pdfs/'.$name;
    }

    public function test_extractor_on_text_pdf()
    {
        $extractor = new PdfTextExtractor($this->parser);
        $text = $extractor->extract($this->fixture('text-invoice.pdf'));
        $this->assertEquals(true, $text['has_text']);
        $this->assertNotEmpty($text['pages']);
        $this->assertEquals(1, $text['pages'][0]['page_number']);
        $this->assertStringContainsString('13983', $text['pages'][0]['text']);
    }

    public function test_extractor_on_scanned_pdf()
    {
        $extractor = new PdfTextExtractor($this->parser);
        $text = $extractor->extract($this->fixture('scanned-invoice.pdf'));
        $this->assertEquals(false, $text['has_text']);
    }

    public function test_extractor_on_invalid_path()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not readable');
        $extractor = new PdfTextExtractor($this->parser);
        $extractor->extract($this->fixture('nonexistent.pdf'));
    }
}
