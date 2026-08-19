<?php

namespace App\Services\Extraction;

/**
 * One line item read off an invoice.
 *
 * Every field is nullable because this describes what a language model managed to find on a
 * PDF, not a valid database row. Extraction is allowed to come back half-empty; deciding
 * whether that is good enough is the QualityScorer's job, later.
 *
 * `partNumber` is here deliberately. The source app's LineItem had no such field and
 * db_writer.php hardcoded part_number=null — which silently disabled the entire deterministic
 * matching path, since it requires a part-number hit. See REBUILD_PLAN §2.
 */
class InvoiceLineData
{
    public function __construct(
        public readonly ?string $partNumber = null,
        public readonly ?string $description = null,
        public readonly ?float $quantity = null,
        public readonly ?float $unitPrice = null,
        public readonly ?float $amount = null,
    ) {}

    /**
     * Builds one line from the model's decoded JSON.
     *
     * A `static` method that returns an instance of its own class is called a named
     * constructor — it lets the messy "which key did the model use this time?" logic live in
     * one place instead of at every call site.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            partNumber: self::stringOrNull($row['part_number'] ?? null),
            description: self::stringOrNull($row['description'] ?? null),
            quantity: self::floatOrNull($row['quantity'] ?? null),
            unitPrice: self::floatOrNull($row['unit_price'] ?? null),
            amount: self::floatOrNull($row['total'] ?? $row['amount'] ?? null),
        );
    }

    /**
     * True when the model returned a row with nothing usable in it. The source app filtered
     * these out before writing, and so should we — they are model noise, not line items.
     */
    public function isEmpty(): bool
    {
        return $this->partNumber === null
            && $this->description === null
            && $this->quantity === null
            && $this->unitPrice === null
            && $this->amount === null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return is_numeric($trimmed = trim($value)) ? (float) $trimmed : null;
    }
}
