<?php

namespace Tests\Unit;

use App\Support\TransactionIdExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionIdExtractorTest extends TestCase
{
    #[DataProvider('labelledTextProvider')]
    public function test_it_extracts_the_value_following_a_known_label(string $text, string $expected): void
    {
        $this->assertSame($expected, TransactionIdExtractor::extract($text));
    }

    public static function labelledTextProvider(): array
    {
        return [
            'Transaction ID with colon' => ['Transaction ID: 123456789012', '123456789012'],
            'Transaction Id (mixed case label)' => ['Transaction Id 987654321098', '987654321098'],
            'Transaction No' => ['Transaction No: TXN20260915001', 'TXN20260915001'],
            'Transaction Number' => ['Transaction Number - ABC123XYZ789', 'ABC123XYZ789'],
            'Txn ID short form' => ['Txn ID: 445566778899', '445566778899'],
            'Txn No short form' => ['Txn No. 998877665544', '998877665544'],
            'Reference Number' => ['Reference Number: 112233445566', '112233445566'],
            'Reference No' => ['Reference No: REF998877', 'REF998877'],
            'Ref ID' => ['Ref ID : 556677889900', '556677889900'],
            'UTR alone' => ['UTR 334455667788', '334455667788'],
            'UTR Number' => ['UTR Number: 223344556677', '223344556677'],
            'UPI Transaction ID' => ['UPI Transaction ID: 667788990011', '667788990011'],
            'UPI Ref No' => ['UPI Ref No: 778899001122', '778899001122'],
            'label embedded in a full screenshot dump' => [
                "Payment Successful\nAmount Paid: ₹500\nDate: 15 Sep 2026, 10:30 AM\nUPI Transaction ID: 445511667788\nPaid to: Merchant Store",
                '445511667788',
            ],
            'label split across a line wrap' => ["Transaction ID\n445599661122", '445599661122'],
        ];
    }

    #[DataProvider('unlabelledTextProvider')]
    public function test_it_does_not_extract_unrelated_numbers(string $text): void
    {
        $this->assertNull(TransactionIdExtractor::extract($text));
    }

    public static function unlabelledTextProvider(): array
    {
        return [
            'plain amount' => ['Amount: ₹500'],
            'phone number with no label' => ['Contact us: 9876543210'],
            'date and time' => ['Date: 15/09/2026 Time: 10:30:45'],
            'account number labeled only as account' => ['Account Number: 445566778899'],
            'order id (not a payment reference label)' => ['Order ID: 998877665544'],
            'no numbers at all' => ['Payment completed successfully. Thank you for your purchase.'],
            'empty string' => [''],
        ];
    }

    public function test_it_ignores_a_short_value_below_the_minimum_length(): void
    {
        // A 4-character value after a real label - shouldn't match; too
        // short to plausibly be a real transaction/reference ID, more
        // likely a stray OCR fragment.
        $this->assertNull(TransactionIdExtractor::extract('Ref No: AB12'));
    }
}
