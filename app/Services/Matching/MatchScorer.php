<?php

namespace App\Services\Matching;

use App\Enums\MatchStrategy;
use App\Models\PurchaseOrder;
use App\Services\Extraction\InvoiceData;

class MatchScorer
{
    private const PO_WEIGHT = 0.5;

    private const LINE_WEIGHT = 0.45;

    private const DATE_WEIGHT = 0.05;

    public function __construct(
        private LineMatcher $lines,
        private DateProximity $dates,
    ) {}

    public function score(InvoiceData $invoice, PurchaseOrder $po): float
    {
        return (self::PO_WEIGHT * ($this->poNumberMatches($invoice, $po) ? 1 : 0))
            + (self::LINE_WEIGHT * $this->lines->score($invoice, $po))
            + (self::DATE_WEIGHT * $this->dates->score($invoice->date, $po->po_date));
    }

    public function strategy(InvoiceData $invoice, PurchaseOrder $po): MatchStrategy
    {
        if ($this->poNumberMatches($invoice, $po) && $this->lines->hasConfirmingLine($invoice, $po) === true) {
            return MatchStrategy::Exact;
        }

        return MatchStrategy::Fuzzy;
    }

    private function poNumberMatches(InvoiceData $invoice, PurchaseOrder $po): bool
    {
        if ($invoice->poNumber === null) {
            return false;
        }

        return trim($invoice->poNumber) === trim($po->po_number);
    }
}
