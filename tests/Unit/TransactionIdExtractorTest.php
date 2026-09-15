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
            'UPI Transaction ID with colon' => ['UPI Transaction ID: 426819374625', '426819374625'],
            'Transaction ID with colon' => ['Transaction ID: 123456789012', '123456789012'],
            'Transaction Id (mixed case label)' => ['Transaction Id 987654321098', '987654321098'],
            'Transaction No' => ['Transaction No: TXN20260915001', 'TXN20260915001'],
            'Transaction Number' => ['Transaction Number - ABC123XYZ789', 'ABC123XYZ789'],
            'Transaction Ref' => ['Transaction Ref: 665544332211', '665544332211'],
            'Transaction Reference' => ['Transaction Reference: 556677889900', '556677889900'],
            'Txn ID short form' => ['Txn ID: 445566778899', '445566778899'],
            'Txn No short form' => ['Txn No. 998877665544', '998877665544'],
            'Reference Number' => ['Reference Number: 112233445566', '112233445566'],
            'Reference No' => ['Reference No: 998877443322', '998877443322'],
            'UTR with colon' => ['UTR: 123456789012', '123456789012'],
            'UTR Number' => ['UTR Number: 223344556677', '223344556677'],
            'UPI Ref No' => ['UPI Ref No: 778899001122', '778899001122'],
            'UPI Reference Number' => ['UPI Reference Number: 990011223344', '990011223344'],
            'label embedded in a full screenshot dump' => [
                "Payment Successful\nAmount Paid: ₹500\nDate: 15 Sep 2026, 10:30 AM\nUPI Transaction ID: 445511667788\nPaid to: Merchant Store",
                '445511667788',
            ],
            'label and value separated by a newline (OCR line wrap)' => ["Transaction ID\n445599661122", '445599661122'],
            'UTR label and value separated by a newline' => ["UTR\n778811223344", '778811223344'],
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
            'random 12-digit number with no label anywhere nearby' => ['Your code is 445566778899, keep it safe.'],
            'no numbers at all' => ['Payment completed successfully. Thank you for your purchase.'],
            'empty string' => [''],
            // Regression cases for the real false positive this class had:
            // bare "Ref"/"Reference"/"Transaction" with no qualifying
            // suffix word matched on their own, e.g. "Ref: <anything>" in a
            // totally unrelated context. "Ref ID"/"Ref No" (without a
            // "Transaction"/"UPI" prefix) are deliberately NOT in the
            // approved label list for the same reason - too generic, used
            // for all kinds of non-payment reference numbers.
            'bare "Ref" with no qualifying label word' => ['Customer Ref: 445566778899'],
            'bare "Reference" with no qualifying label word' => ['For queries, Reference: SUPPORT4455'],
            'bare "Ref ID" (no Transaction/UPI prefix - not an approved label)' => ['Ref ID: 556677889900'],
            'bare "Ref No" (no Transaction/UPI prefix - not an approved label)' => ['Ref No: 887766554433'],
            'bare "Transaction" with no qualifying suffix word' => ['Transaction summary code: 445566778899'],
            'bare "Txn" with no qualifying suffix word' => ['Txn details: 445566778899'],
            // A realistic OCR dump of a screenshot with genuinely no
            // transaction/reference label at all - must stay unmatched.
            'full screenshot dump with no transaction label' => [
                "Payment Successful\nAmount Paid: ₹500\nDate: 15 Sep 2026, 10:30 AM\nPaid to: Merchant Store\nUPI ID: merchant@okhdfcbank",
            ],
        ];
    }

    public function test_it_ignores_a_short_value_below_the_minimum_length(): void
    {
        // A 4-character value after a real, approved label - shouldn't
        // match; too short to plausibly be a real transaction/reference ID,
        // more likely a stray OCR fragment.
        $this->assertNull(TransactionIdExtractor::extract('Txn No: AB12'));
    }
}
