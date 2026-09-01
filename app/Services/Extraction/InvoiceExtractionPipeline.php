<?php

namespace App\Services\Extraction;

use RuntimeException;

class InvoiceExtractionPipeline
{
    public function extract(string $path): InvoiceData
    {
        $text = (new PdfTextExtractor)->extract($path);
        if ($text === '') {
            throw new RuntimeException("The PDF has no text layer. $path");
        }
        $raw = (new LlmInvoiceExtractor)->extract($text);
        $array = (new JsonResponseParser)->parse($raw);
        $invoice = InvoiceData::fromArray($array);

        return $invoice;
    }
}
