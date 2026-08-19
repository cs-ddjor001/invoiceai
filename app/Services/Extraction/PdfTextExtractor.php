<?php

namespace App\Services\Extraction;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Reads the text layer out of a PDF.
 *
 * This is a reader, not an InvoiceExtractor — it knows nothing about invoices. It is the
 * collaborator the LLM driver uses to get something worth prompting with; the future vision
 * driver skips it entirely.
 *
 * Replaces pdfplumber. The tradeoff accepted in REBUILD_PLAN §3: smalot has no table
 * detection, so layout text goes to the model instead of pdfplumber's structured tables.
 */
class PdfTextExtractor
{
    /**
     * Type-hinting Parser lets the container build it. Nothing registers Parser anywhere —
     * Laravel sees a concrete class with a no-argument constructor and just instantiates it.
     * The payoff is that a test can hand in its own instance without touching this file.
     */
    public function __construct(private readonly Parser $parser) {}

    /**
     * `has_text` is the branch point the pipeline needs: exactly one of the 39 sample PDFs
     * (inv_5951865995.pdf) is a scan with no text layer, and that is the case the deferred
     * vision driver exists to handle. Returning a flag beats making every caller re-derive
     * "were all the page strings empty?".
     *
     * @return array{file: string, has_text: bool, pages: list<array{page_number: int, text: string}>}
     */
    public function extract(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("PDF is not readable: {$path}");
        }

        try {
            $pdf = $this->parser->parseFile($path);
            $pdfPages = $pdf->getPages();
        } catch (Throwable $e) {
            // smalot throws a mix of exception types for malformed files. Wrapping them gives
            // the Phase 5 job one thing to catch and record on the extraction_runs row.
            throw new RuntimeException(
                "Could not parse PDF [{$path}]: {$e->getMessage()}", previous: $e
            );
        }

        $pages = [];
        $totalLength = 0;

        foreach ($pdfPages as $index => $page) {
            $text = $this->clean($page->getText());
            $totalLength += strlen($text);

            $pages[] = [
                'page_number' => $index + 1,
                'text' => $text,
            ];
        }

        return [
            'file' => basename($path),
            'has_text' => $totalLength > 0,
            'pages' => $pages,
        ];
    }

    /**
     * Ported from clean_text() in the source app's pdfplumber_extractor.py.
     *
     * Collapses runs of spaces and tabs to one, collapses repeated newlines, trims. Purely a
     * context-window economy: whitespace noise costs prompt tokens and teaches the model
     * nothing.
     */
    private function clean(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = preg_replace('/[ \t]+/', ' ', $text) ?? '';
        $text = preg_replace('/\n+/', "\n", $text) ?? '';

        return trim($text);
    }
}
