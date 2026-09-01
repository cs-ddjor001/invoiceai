<?php

namespace App\Services\Extraction;

class InvoiceLineData
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public ?string $partNumber = null,
        public ?string $description = null,
        public ?float $quantity = null,
        public ?float $unitPrice = null,
        public ?float $amount = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            partNumber: self::stringOrNull($data['part_number'] ?? null),
            description: self::stringOrNull($data['description'] ?? null),
            quantity: self::floatOrNull($data['quantity'] ?? null),
            unitPrice: self::floatOrNull($data['unit_price'] ?? null),
            amount: self::floatOrNull($data['total'] ?? null),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }

        $value = str_replace(['$', ',', ' '], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }
}
