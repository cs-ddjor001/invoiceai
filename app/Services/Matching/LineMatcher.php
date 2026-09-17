<?php

namespace App\Services\Matching;

use App\Models\PurchaseOrder;
use App\Services\Extraction\InvoiceData;

class LineMatcher
{
    public function __construct(
        private PartNumberMatcher $partNumbers,
        private PriceTolerance $prices,
    ) {}

    public function hasConfirmingLine(InvoiceData $invoice, PurchaseOrder $po): bool
    {
        return $this->score($invoice, $po) > 0.0;
    }

    public function score(InvoiceData $invoice, PurchaseOrder $po): float
    {
        $checkableLines = 0;
        $matchingLines = 0;

        foreach ($invoice->lines as $line) {
            if ($line->unitPrice === null || $line->partNumber === null) {
                continue;
            }
            $checkableLines++;
            foreach ($po->lines as $poLine) {
                if ($poLine->unit_price === null) {
                    continue;
                }
                if (
                    $this->partNumbers->matches($poLine->part_number, $line->partNumber) &&
                    $this->prices->matches((float) $poLine->unit_price, (float) $line->unitPrice)
                ) {
                    $matchingLines++;
                    break;
                }
            }
        }

        if ($checkableLines === 0) {
            return 0.0;
        }

        return $matchingLines / $checkableLines;
    }
}
