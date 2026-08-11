<?php

namespace App\Models;

use App\Enums\InvoiceSource;
use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $invoice_number
 * @property string|null $po_number
 * @property int|null $vendor_id
 * @property string|null $vendor_name
 * @property string|null $amount
 * @property string|null $subtotal
 * @property string|null $tax
 * @property CarbonImmutable|null $issued_at
 * @property InvoiceStatus $status
 * @property InvoiceSource $source
 * @property int|null $quality_score
 * @property string|null $file_path
 * @property int|null $uploaded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'invoice_number',
    'po_number',
    'vendor_id',
    'vendor_name',
    'amount',
    'subtotal',
    'tax',
    'issued_at',
    'status',
    'source',
    'quality_score',
    'file_path',
    'uploaded_by',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'status' => InvoiceStatus::class,
            'source' => InvoiceSource::class,
            'amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
        ];
    }

    /**
     * The vendor this invoice was billed by, once resolved. Null until extraction identifies
     * one — `vendor_name` holds the raw extracted text in the meantime.
     *
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * The AP user who uploaded the file. Second argument is required: Eloquent would infer
     * `vendor_id` from the method name `uploader`, and the column is `uploaded_by`.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * Every match attempt against this invoice — one row per strategy. Replaces the source
     * app's `matched_po_id` / `ai_matched_po_id` columns.
     *
     * @return HasMany<InvoiceMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(InvoiceMatch::class);
    }

    /**
     * @return HasMany<ExtractionRun, $this>
     */
    public function extractionRuns(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }
}
