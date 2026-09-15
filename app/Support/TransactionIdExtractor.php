<?php

namespace App\Support;

/**
 * Pulls a transaction/reference ID out of OCR'd payment-screenshot text.
 *
 * Deliberately conservative: rather than grabbing the first long number
 * anywhere in the text (which would just as happily match a phone number,
 * an amount, a date, or an account number), this only captures a value that
 * immediately follows one of a fixed, known set of COMPLETE transaction/
 * reference label phrases commonly seen on UPI/payment confirmation
 * screenshots. No match is a completely normal, expected outcome - see
 * PaymentProofOcrService.
 */
class TransactionIdExtractor
{
    /**
     * Complete label phrases only - not a word1 x word2 cross-product.
     * A cross-product ("Ref"+"No" = "Ref No", but also bare "Ref" alone
     * since the suffix was optional) is exactly what caused a real false
     * positive: bare "Reference"/"Ref"/"Transaction"/"Txn"/"UTR" matched on
     * their own in unrelated contexts. Every phrase here is the whole
     * label, so a bare "Ref:" with no qualifying word cannot match at all.
     *
     * Longer/more specific phrases are listed before shorter ones that are
     * textual prefixes of them (e.g. "UTR Number" before bare "UTR",
     * "Transaction Number" before "Transaction No") - PCRE tries
     * alternatives in order, so this ensures e.g. "UTR Number: 123..."
     * matches the whole label "UTR Number" rather than matching just "UTR"
     * and then mistakenly trying to capture "Number" as the value.
     */
    private const LABELS = [
        'UPI\s+Transaction\s+ID',
        'UPI\s+Reference\s+Number',
        'UPI\s+Ref\s+No',
        'Transaction\s+Number',
        'Transaction\s+Reference',
        'Transaction\s+Ref',
        'Transaction\s+No',
        'Transaction\s+ID',
        'Reference\s+Number',
        'Reference\s+No',
        'UTR\s+Number',
        'UTR',
        'Txn\s+ID',
        'Txn\s+No',
    ];

    public static function extract(string $ocrText): ?string
    {
        $normalized = self::normalize($ocrText);

        $labels = implode('|', self::LABELS);
        $pattern = '/\b(?:'.$labels.')\b\.?[\s:\-]{0,5}([A-Za-z0-9]{6,30})\b/i';

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
