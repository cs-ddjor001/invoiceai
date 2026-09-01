<?php

namespace Tests\Unit;

use App\Services\Extraction\PdfTextExtractor;
use PHPUnit\Framework\TestCase;

class PdfTextExtractorTest extends TestCase
{
    private function fixture(string $name): string
    {
        return dirname(__DIR__).'/fixtures/pdfs/'.$name;
    }

    public function test_extractor_on_text_pdf()
    {
        $extractor = new PdfTextExtractor;
        $text = $extractor->extract($this->fixture('text-invoice.pdf'));
        $this->assertStringContainsString('13983', $text);
    }

    public function test_extractor_on_scanned_pdf()
    {
        $extractor = new PdfTextExtractor;
        $text = $extractor->extract($this->fixture('scanned-invoice.pdf'));
        $this->assertSame('', $text);
    }

    public function test_extractor_on_invalid_path()
    {
        $this->expectException(\RuntimeException::class);
        $extractor = new PdfTextExtractor;
        $extractor->extract($this->fixture('nonexistent.pdf'));
    }
}
