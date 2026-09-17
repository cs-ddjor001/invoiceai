<?php

namespace Tests\Unit;

use App\Services\Matching\DateProximity;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateProximityTest extends TestCase
{
    public function test_exact_date(): void
    {
        $dateProximity = new DateProximity;
        $invoiceDate = '2026-03-13';
        $poDate = CarbonImmutable::parse('2026-03-13');
        $actualScore = $dateProximity->score($invoiceDate, $poDate);
        $expectedScore = 1.0;
        $this->assertEqualsWithDelta($expectedScore, $actualScore, 0.01);
    }

    public function test_different_date(): void
    {
        $dateProximity = new DateProximity;
        $invoiceDate = '2025-02-08';
        $poDate = CarbonImmutable::parse('2024-10-31');
        $actualScore = $dateProximity->score($invoiceDate, $poDate);
        $expectedScore = 0.44;
        $this->assertEqualsWithDelta($expectedScore, $actualScore, 0.01);
    }

    public function test_another_different_date(): void
    {
        $dateProximity = new DateProximity;
        $invoiceDate = '2026-01-16';
        $poDate = CarbonImmutable::parse('2025-04-16');
        $actualScore = $dateProximity->score($invoiceDate, $poDate);
        $expectedScore = 0.0;
        $this->assertEqualsWithDelta($expectedScore, $actualScore, 0.01);
    }

    public function test_null_invoice_date(): void
    {
        $dateProximity = new DateProximity;
        $invoiceDate = null;
        $poDate = CarbonImmutable::parse('2026-09-07');
        $actualScore = $dateProximity->score($invoiceDate, $poDate);
        $expectedScore = 0.0;
        $this->assertEqualsWithDelta($expectedScore, $actualScore, 0.01);
    }

    public function test_invalid_invoice_date(): void
    {
        $dateProximity = new DateProximity;
        $invoiceDate = 'invalid-date';
        $poDate = CarbonImmutable::parse('2026-09-07');
        $actualScore = $dateProximity->score($invoiceDate, $poDate);
        $expectedScore = 0.0;
        $this->assertEqualsWithDelta($expectedScore, $actualScore, 0.01);
    }
}
