<?php

namespace App\Services\Matching;

class PriceTolerance
{
    public function matches(float $poUnitPrice, float $invoiceUnitPrice): bool
    {
        $tolerance = 0.02;
        if ($poUnitPrice > 5000) {
            $tolerance = 0.01;
        } elseif ($poUnitPrice < 100) {
            $tolerance = 0.05;
        }

        return abs($poUnitPrice - $invoiceUnitPrice) <= $poUnitPrice * $tolerance;
    }
}
