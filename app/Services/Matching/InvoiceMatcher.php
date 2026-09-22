<?php

namespace App\Services\Matching;

use App\Enums\MatchStrategy;
use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Models\PurchaseOrder;
use App\Services\Extraction\InvoiceData;
use Illuminate\Support\Facades\DB;

class InvoiceMatcher
{
    public function __construct(private MatchScorer $scorer) {}

    public function match(Invoice $invoice): ?InvoiceMatch
    {
        if ($invoice->po_number === null) {
            return null;
        }

        $purchaseOrder = PurchaseOrder::where('po_number', $invoice->po_number)->first();
        if ($purchaseOrder === null) {
            return null;
        }
        $data = InvoiceData::fromModel($invoice);

        return DB::transaction(function () use ($invoice, $purchaseOrder, $data) {
            InvoiceMatch::where('invoice_id', $invoice->id)
                ->whereIn('strategy', [MatchStrategy::Exact, MatchStrategy::Fuzzy])
                ->delete();

            return InvoiceMatch::updateOrCreate([
                'invoice_id' => $invoice->id,
                'purchase_order_id' => $purchaseOrder->id,
                'strategy' => $this->scorer->strategy($data, $purchaseOrder),
            ], [
                'confidence' => $this->scorer->score($data, $purchaseOrder),
            ]);
        });
    }
}
