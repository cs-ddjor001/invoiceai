<?php

namespace Tests\Unit;

use App\Services\Extraction\InvoiceData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InvoiceDataTest extends TestCase
{
    #[DataProvider('moneyCases')]
    public function test_money_is_cleaned(mixed $input, ?float $expected): void
    {
        $this->assertSame($expected, InvoiceData::fromArray(['total' => $input])->total);
    }

    #[DataProvider('invoiceNumberCases')]
    public function test_invoice_number_is_cleaned(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, InvoiceData::fromArray(['invoice_number' => $input])->invoiceNumber);
    }

    #[DataProvider('dateCases')]
    public function test_date_is_normalized(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, InvoiceData::fromArray(['date' => $input])->date);
    }

    public static function moneyCases(): array
    {
        return [
            'dollar sign and comma' => ['$88,960.00', 88960.0],
            'unreadable' => ['N/A',        null],
        ];
    }

    public static function dateCases(): array
    {
        return [
            'valid date' => ['2024-06-15', '2024-06-15'],
            '2025-02-08' => ['2025-02-08', '2025-02-08'],
            '2/8/2025' => ['2/8/2025', '2025-02-08'],
            'February 8, 2025' => ['February 8, 2025', '2025-02-08'],
            'tomorrow' => ['tomorrow', null],
            '2025' => ['2025', null],
            '0000-00-00' => ['0000-00-00', null],
            'N/A' => ['N/A', null],
        ];
    }

    public static function invoiceNumberCases(): array
    {
        return [
            'valid string' => ['13983', '13983'],
            'empty string' => ['', null],
            'array' => [['13983'], null],
            'padded with spaces' => ['  13983  ', '13983'],
        ];
    }
}
