<?php

namespace App\Enums;

/**
 * Which driver produced an extraction run. Maps to the Phase 3 implementations of the
 * `InvoiceExtractor` interface — Vision stays declared but unimplemented until the
 * PDF-to-image dependency is resolved.
 */
enum ExtractorType: string
{
    case PdfText = 'pdf_text';
    case Llm = 'llm';
    case Vision = 'vision';
}
