<?php

namespace App\Support;

/**
 * Pulls a transaction/reference ID out of OCR'd payment-screenshot text.
 *
 * Deliberately conservative: rather than grabbing the first long number
 * anywhere in the text (which would just as happily match a phone number,
 * an amount, a date, or an account number), this only captures a value that
 * immediately follows one of a known set of transaction/reference labels
 * commonly seen on UPI/payment confirmation screenshots. No match is a
 * completely normal, expected outcome - see PaymentProofOcrService.
 */
class TransactionIdExtractor
{
    /**
     * Label words that can precede a transaction/reference value, e.g.
     * "Transaction ID", "Txn No", "UPI Ref No", "Reference Number", "UTR".
     * Built from these two groups rather than one giant literal-phrase list,
     * so "UPI Transaction Ref No" and "Txn ID" are both covered without
     * spelling out every label/suffix combination by hand.
     */
    private const LABEL_WORDS = 'Transaction|Txn|Reference|Ref|UTR';
    private const SUFFIX_WORDS = 'ID|No|Number|Ref';

    public static function extract(string $ocrText): ?string
    {
        $normalized = self::normalize($ocrText);

        $pattern = '/\b(?:UPI\s+)?(?:'.self::LABEL_WORDS.')\.?\s*(?:(?:'.self::SUFFIX_WORDS.')\.?)?'
            .'[\s:\-]{0,5}([A-Za-z0-9]{6,30})\b/i';

        if (preg_match($pattern, $normalized, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * OCR output is noisy: inconsistent whitespace/line breaks and stray
     * characters around otherwise-clean text. Collapsing whitespace is
     * enough for the regex above (\s already spans line breaks), without
     * trying to "fix" the OCR text itself.
     */
    private static function normalize(string $text): string
    {
        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
    }
}
