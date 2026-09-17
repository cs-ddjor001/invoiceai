<?php

namespace App\Services\Matching;

class PartNumberMatcher
{
    private const MIN_PREFIX_LENGTH = 7;

    public function matches(?string $poPartNumber, ?string $invoicePartNumber): bool
    {
        if ($poPartNumber === null || $invoicePartNumber === null) {
            return false;
        }

        $poPartNumber = $this->normalize($poPartNumber);
        $invoicePartNumber = $this->normalize($invoicePartNumber);

        if ($poPartNumber === '' || $invoicePartNumber === '') {
            return false;
        }

        if ($poPartNumber === $invoicePartNumber) {
            return true;
        } elseif (str_starts_with($poPartNumber, $invoicePartNumber) && strlen($invoicePartNumber) >= self::MIN_PREFIX_LENGTH) {
            return true;
        } elseif (str_starts_with($invoicePartNumber, $poPartNumber) && strlen($poPartNumber) >= self::MIN_PREFIX_LENGTH) {
            return true;
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($value)) ?? '';
    }
}
