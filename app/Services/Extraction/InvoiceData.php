<?php

namespace App\Services\Extraction;

use Carbon\CarbonImmutable;
use Throwable;

class InvoiceData
{
    /**
     * @param  array<InvoiceLineData>  $lines
     */
    public function __construct(
        public ?string $invoiceNumber = null,
        public ?string $poNumber = null,
        public ?string $date = null,
        public ?float $subtotal = null,
        public ?float $tax = null,
        public ?float $total = null,
        public array $lines = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $lines = [];

        foreach ($data['line_items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lines[] = InvoiceLineData::fromArray($row);
        }

        return new self(
            invoiceNumber: self::stringOrNull($data['invoice_number'] ?? null),
            poNumber: self::stringOrNull($data['po_number'] ?? null),
            date: self::dateOrNull($data['date'] ?? null),
            subtotal: self::floatOrNull($data['subtotal'] ?? null),
            tax: self::floatOrNull($data['tax'] ?? null),
            total: self::floatOrNull($data['total'] ?? null),
            lines: $lines,
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

    private static function dateOrNull(mixed $value): ?string
    {
        $string = self::stringOrNull($value);
        if ($string === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($string)->format('Y-m-d');
        } catch (Throwable $e) {
            return null;
        }
    }
}
