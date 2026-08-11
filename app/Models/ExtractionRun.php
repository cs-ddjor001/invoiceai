<?php

namespace App\Models;

use App\Enums\ExtractorType;
use Carbon\CarbonImmutable;
use Database\Factories\ExtractionRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property ExtractorType $extractor
 * @property array<string, mixed>|null $raw_response
 * @property int|null $duration_ms
 * @property string $status
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['invoice_id', 'extractor', 'raw_response', 'duration_ms', 'status', 'error'])]
class ExtractionRun extends Model
{
    /** @use HasFactory<ExtractionRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'extractor' => ExtractorType::class,
            'raw_response' => 'array',
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
