<?php

namespace App\Services\Extraction;

use RuntimeException;
use Smalot\PdfParser\Parser;

class PdfTextExtractor
{
    public function extract(string $path): string
    {
        if (! is_readable($path)) {
            throw new RuntimeException("File not readable: $path");
        }

        $parser = new Parser;
        $pdf = $parser->parseFile($path);

        return $pdf->getText();
    }
}
