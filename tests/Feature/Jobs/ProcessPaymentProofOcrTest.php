<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessPaymentProofOcr;
use App\Models\Complaint;
use App\Models\ComplaintReason;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentProofOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessPaymentProofOcrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeComplaintWithPaymentProof(): Complaint
    {
        $role = Role::firstOrCreate(['name' => 'user'], ['description' => 'user']);
        $customer = User::factory()->create(['role_id' => $role->id, 'status' => 'approved']);
        $reason = ComplaintReason::create(['name' => 'Reason '.uniqid(), 'priority' => 'LOW', 'active' => true]);

        $path = UploadedFile::fake()->image('proof.jpg')->storeAs('uploads/payment-proofs', 'proof.jpg', 'local');

        return Complaint::create([
            'user_id' => $customer->id,
            'reason_id' => $reason->id,
            'message' => 'Please refund my order.',
            'priority' => 'LOW',
            'status' => 'pending',
            'payment_proof_path' => $path,
            'transaction_id' => null,
            'ocr_status' => 'pending',
        ]);
    }

    private function fakeOcrResult(array $result): void
    {
        $mock = \Mockery::mock(PaymentProofOcrService::class);
        $mock->shouldReceive('process')->once()->andReturn($result);
        $this->app->instance(PaymentProofOcrService::class, $mock);
    }

    public function test_handle_stores_extracted_transaction_id_on_the_complaint(): void
    {
        $this->fakeOcrResult(['status' => 'extracted', 'transaction_id' => 'ABC123']);
        $complaint = $this->makeComplaintWithPaymentProof();

        (new ProcessPaymentProofOcr($complaint))->handle(app(PaymentProofOcrService::class));

        $complaint->refresh();
        $this->assertSame('extracted', $complaint->ocr_status);
        $this->assertSame('ABC123', $complaint->transaction_id);
    }

    public function test_handle_stores_not_found_result(): void
    {
        $this->fakeOcrResult(['status' => 'not_found', 'transaction_id' => null]);
        $complaint = $this->makeComplaintWithPaymentProof();

        (new ProcessPaymentProofOcr($complaint))->handle(app(PaymentProofOcrService::class));

        $complaint->refresh();
        $this->assertSame('not_found', $complaint->ocr_status);
        $this->assertNull($complaint->transaction_id);
    }

    public function test_handle_stores_failed_result_without_throwing(): void
    {
        $this->fakeOcrResult(['status' => 'failed', 'transaction_id' => null]);
        $complaint = $this->makeComplaintWithPaymentProof();

        (new ProcessPaymentProofOcr($complaint))->handle(app(PaymentProofOcrService::class));

        $complaint->refresh();
        $this->assertSame('failed', $complaint->ocr_status);
    }

    /**
     * Defensive edge case: not reachable in practice (payment proof is
     * required at submission), but the job must not crash if it somehow
     * ever ran against a complaint with no proof on file.
     */
    public function test_handle_does_nothing_when_there_is_no_payment_proof_path(): void
    {
        $role = Role::firstOrCreate(['name' => 'user'], ['description' => 'user']);
        $customer = User::factory()->create(['role_id' => $role->id, 'status' => 'approved']);
        $reason = ComplaintReason::create(['name' => 'Reason '.uniqid(), 'priority' => 'LOW', 'active' => true]);
        $complaint = Complaint::create([
            'user_id' => $customer->id,
            'reason_id' => $reason->id,
            'message' => 'Legacy complaint.',
            'priority' => 'LOW',
            'status' => 'pending',
            'payment_proof_path' => null,
            'ocr_status' => 'pending',
        ]);

        $mock = \Mockery::mock(PaymentProofOcrService::class);
        $mock->shouldNotReceive('process');
        $this->app->instance(PaymentProofOcrService::class, $mock);

        (new ProcessPaymentProofOcr($complaint))->handle(app(PaymentProofOcrService::class));

        $complaint->refresh();
        $this->assertSame('pending', $complaint->ocr_status);
    }

    /**
     * failed() is Laravel's hook for when handle() itself threw on every
     * retry attempt - reached only for a genuine infrastructure failure
     * (PaymentProofOcrService never throws). Must leave the complaint in a
     * conclusive state rather than stuck on "pending" forever.
     */
    public function test_failed_marks_the_complaint_ocr_status_as_failed(): void
    {
        $complaint = $this->makeComplaintWithPaymentProof();

        (new ProcessPaymentProofOcr($complaint))->failed(new \RuntimeException('Simulated infrastructure failure.'));

        $complaint->refresh();
        $this->assertSame('failed', $complaint->ocr_status);
    }
}
