<?php

namespace App\Services\Extraction;

class InvoiceData
{
    /**
     * Invoice data extracted from a PDF.
     *
     * @param  array<InvoiceLineData>  $lines
     */
    public function __construct(
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $poNumber = null,
        public readonly ?string $vendorName = null,
        public readonly ?string $date = null,
        public readonly ?float $subtotal = null,
        public readonly ?float $tax = null,
        public readonly ?float $total = null,
        public readonly array $lines = [],
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $data  the model's decoded JSON
     */
    public static function fromArray(array $data): self
    {
        return new self(
            // Keys are snake_case because that is what the model sends back. The properties
            // are camelCase because that is PHP convention. Translating between the two is
            // the reason this class exists.
            invoiceNumber: self::stringOrNull($data['invoice_number'] ?? null),
            poNumber: self::stringOrNull($data['po_number'] ?? null),
            vendorName: self::stringOrNull($data['vendor_name'] ?? null),
            date: self::stringOrNull($data['date'] ?? null),
            subtotal: self::floatOrNull($data['subtotal'] ?? null),
            tax: self::floatOrNull($data['tax'] ?? null),
            total: self::floatOrNull($data['total'] ?? null),
            lines: array_filter(
                array_map(
                    fn ($line) => InvoiceLineData::fromArray($line),
                    $data['line_items'] ?? []
                ),
                fn ($line) => ! $line->isEmpty()
            ),
        );
    }

    public function isEmpty(): bool
    {
        return $this->invoiceNumber === null
        && $this->poNumber === null
        && $this->vendorName === null
        && $this->date === null
        && $this->subtotal === null
        && $this->tax === null
        && $this->total === null
        && empty($this->lines);
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
