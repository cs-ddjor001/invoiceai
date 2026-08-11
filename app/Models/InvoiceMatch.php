<?php

namespace App\Models;

use App\Enums\MatchStrategy;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One match attempt: which strategy ran, what it concluded, and whether a human accepted it.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $purchase_order_id
 * @property MatchStrategy $strategy
 * @property float|null $confidence
 * @property array<string, mixed>|null $reasoning
 * @property bool $is_accepted
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'invoice_id',
    'purchase_order_id',
    'strategy',
    'confidence',
    'reasoning',
    'is_accepted',
    'reviewed_by',
])]
class InvoiceMatch extends Model
{
    /** @use HasFactory<InvoiceMatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'strategy' => MatchStrategy::class,
            'confidence' => 'decimal:4',
            'reasoning' => 'array',
            'is_accepted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * The AP user who accepted or rejected this match. Explicit foreign key: the method is
     * named `reviewer`, so Eloquent would look for `reviewer_id`.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
