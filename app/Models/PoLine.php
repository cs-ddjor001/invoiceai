<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PoLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The decimal-cast columns are typed string, not float — Eloquent's decimal cast returns a
 * string so the value never passes through binary floating point.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $line_num
 * @property string|null $part_number
 * @property string|null $description
 * @property string|null $uom
 * @property string|null $qty_ordered
 * @property string|null $qty_delivered
 * @property string|null $qty_cancelled
 * @property string|null $unit_price
 * @property string|null $amt_invoiced
 * @property string|null $status
 * @property string|null $clin
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'purchase_order_id',
    'line_num',
    'part_number',
    'description',
    'uom',
    'qty_ordered',
    'qty_delivered',
    'qty_cancelled',
    'unit_price',
    'amt_invoiced',
    'status',
    'clin',
])]
class PoLine extends Model
{
    /** @use HasFactory<PoLineFactory> */
    use HasFactory;

    /**
     * Precision mirrors the migration: quantities and unit prices are decimal(12,4), the
     * invoiced amount is decimal(14,2). Casting a 4dp column to 2dp would round away
     * precision the 1% price-tolerance check depends on.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:4',
            'qty_delivered' => 'decimal:4',
            'qty_cancelled' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amt_invoiced' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
