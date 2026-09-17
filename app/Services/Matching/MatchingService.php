<?php

namespace App\Services\Matching;

use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Services\Extraction\InvoiceData;

class MatchingService
{
    public function __construct(private MatchScorer $scorer) {}

    public function match(Invoice $invoice, InvoiceData $data): ?InvoiceMatch {}
}
