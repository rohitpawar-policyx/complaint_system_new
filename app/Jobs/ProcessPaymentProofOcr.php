<?php

namespace App\Jobs;

use App\Models\Complaint;
use App\Services\PaymentProofOcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs OCR on a complaint's payment proof off the request cycle. Tesseract
 * can take a second or more per image, and a customer shouldn't sit waiting
 * on that just to get an "your complaint was created" confirmation - OCR is
 * an enhancement to the complaint, not a precondition for it existing.
 *
 * PaymentProofOcrService itself is designed to never throw (see its own
 * docblock) - a 'failed' ocr_status from a clean run is a normal, SUCCESSFUL
 * outcome for this job, not a retryable failure. The only things that can
 * actually make this job fail are genuine infrastructure problems (e.g. a
 * dropped DB connection on the final `update()` call), which is exactly what
 * $tries/$backoff exist to retry.
 */
class ProcessPaymentProofOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public Complaint $complaint) {}

    public function handle(PaymentProofOcrService $ocrService): void
    {
        // Defensive, not expected in practice: this job is only ever
        // dispatched right after a complaint is created with a payment
        // proof already attached (payment proof is required at submission).
        if ($this->complaint->payment_proof_path === null) {
            return;
        }

        $absolutePath = Storage::disk('local')->path($this->complaint->payment_proof_path);
        $result = $ocrService->process($absolutePath);

        $this->complaint->update([
            'ocr_status' => $result['status'],
            'transaction_id' => $result['transaction_id'],
        ]);
    }

    /**
     * Reached only if handle() itself threw on every attempt (OCR failures
     * are never exceptions here - see the class docblock). Sets ocr_status
     * to 'failed' explicitly so the admin UI shows a conclusive result
     * instead of "pending" forever.
     */
    public function failed(Throwable $exception): void
    {
        $this->complaint->update(['ocr_status' => 'failed']);
    }
}
