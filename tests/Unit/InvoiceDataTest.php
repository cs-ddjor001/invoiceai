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

    public static function moneyCases(): array
    {
        return [
            'dollar sign and comma' => ['$88,960.00', 88960.0],
            'unreadable' => ['N/A',        null],
        ];
    }

    #[DataProvider('invoiceNumberCases')]
    public function test_invoice_number_is_cleaned(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, InvoiceData::fromArray(['invoice_number' => $input])->invoiceNumber);
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
