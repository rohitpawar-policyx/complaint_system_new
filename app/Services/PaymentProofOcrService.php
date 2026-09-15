<?php

namespace App\Services;

use App\Support\TransactionIdExtractor;
use Illuminate\Support\Facades\Log;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;

/**
 * Runs Tesseract OCR against a payment-proof image and tries to pull out a
 * transaction/reference ID. OCR is an enhancement, never a requirement: this
 * class is designed so that it is structurally impossible for it to throw -
 * every failure mode (missing binary, unreadable image, no match) ends in a
 * result array with a null transaction_id, never an exception bubbling up
 * into complaint creation.
 */
class PaymentProofOcrService
{
    /**
     * @param  string  $absolutePath  A real filesystem path (not a Storage-
     *                                relative path) - Tesseract shells out
     *                                to the `tesseract` binary and needs an
     *                                actual file to read.
     * @return array{status: string, transaction_id: ?string}
     */
    public function process(string $absolutePath): array
    {
        try {
            $text = (new TesseractOCR($absolutePath))->run();
        } catch (Throwable $exception) {
            // Covers a missing tesseract binary, an unreadable image, or any
            // other failure the underlying process can throw. Logged as a
            // warning (not an error) since this is an expected, handled
            // condition, not something that needs paging anyone.
            Log::warning('Payment proof OCR failed', [
                'path' => $absolutePath,
                'exception' => $exception->getMessage(),
            ]);

            return ['status' => 'failed', 'transaction_id' => null];
        }

        $transactionId = TransactionIdExtractor::extract($text);

        if ($transactionId === null) {
            return ['status' => 'not_found', 'transaction_id' => null];
        }

        return ['status' => 'extracted', 'transaction_id' => $transactionId];
    }
}
