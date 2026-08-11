<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $po_number
 * @property int|null $vendor_id
 * @property string|null $vendor_name
 * @property CarbonImmutable|null $po_date
 * @property string|null $status
 * @property string|null $buyer_name
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'po_number',
    'vendor_id',
    'vendor_name',
    'po_date',
    'status',
    'buyer_name',
])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /**
     * `status` stays a plain string — it holds ADS values like "APPROVED CLOSED" that come
     * straight from the CSV and aren't a set this app controls.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'po_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return HasMany<PoLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PoLine::class);
    }

    /**
     * Every match attempt that landed on this PO, across all strategies.
     *
     * @return HasMany<InvoiceMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(InvoiceMatch::class);
    }
}
