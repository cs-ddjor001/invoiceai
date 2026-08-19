<?php

namespace App\Services\Extraction;

interface InvoiceExtractor
{
    public function extract(string $path): InvoiceData;
}
