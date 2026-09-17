<?php

namespace App\Services\Matching;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

class DateProximity
{
    public function score(?string $invoiceDate, ?CarbonInterface $poDate): float
    {
        if ($invoiceDate === null || $poDate === null) {
            return 0.0;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $invoiceDate);

            if ($parsed === null) {
                return 0.0;
            }
        } catch (Throwable $e) {
            return 0.0;
        }
        $gapInDays = abs($parsed->diffInDays($poDate));

        return max(0.0, 1.0 - ($gapInDays / 180));
    }
}
