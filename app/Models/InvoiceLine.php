<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\InvoiceLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Column names mirror `po_lines` on purpose — Phase 4 compares the two tables field by field.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $line_num
 * @property string|null $part_number
 * @property string|null $description
 * @property string|null $uom
 * @property string|null $qty
 * @property string|null $unit_price
 * @property string|null $amount
 * @property string|null $clin
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'invoice_id',
    'line_num',
    'part_number',
    'description',
    'uom',
    'qty',
    'unit_price',
    'amount',
    'clin',
])]
class InvoiceLine extends Model
{
    /** @use HasFactory<InvoiceLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
