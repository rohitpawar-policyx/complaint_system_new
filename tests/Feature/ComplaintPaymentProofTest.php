<?php

namespace Tests\Feature;

use App\Jobs\ProcessPaymentProofOcr;
use App\Models\Complaint;
use App\Models\ComplaintReason;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentProofOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function makeCustomer(): User
    {
        $role = Role::firstOrCreate(['name' => 'user'], ['description' => 'user']);

        return User::factory()->create(['role_id' => $role->id, 'status' => 'approved']);
    }

    private function makeReason(): ComplaintReason
    {
        return ComplaintReason::create(['name' => 'Reason '.uniqid(), 'priority' => 'LOW', 'active' => true]);
    }

    /** Swaps the real OCR service for a fake that returns a fixed result, without touching Tesseract. */
    private function fakeOcrResult(array $result): void
    {
        $mock = \Mockery::mock(PaymentProofOcrService::class);
        $mock->shouldReceive('process')->andReturn($result);
        $this->app->instance(PaymentProofOcrService::class, $mock);
    }

    /**
     * complaints.store now requires an Idempotency-Key header (see
     * tests/Feature/Idempotency/ComplaintIdempotencyTest.php for the
     * dedicated tests of that behavior) - a fresh one per call here since
     * these tests aren't exercising idempotency itself, just the ordinary
     * creation flow underneath it.
     */
    private function idempotencyKey(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }

    /** 1. Complaint cannot be submitted without payment proof. */
    public function test_complaint_cannot_be_submitted_without_payment_proof(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $response = $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Please refund my order.',
            ]);

        $response->assertSessionHasErrors('payment_proof');
        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_non_image_payment_proof_is_rejected(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $response = $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Please refund my order.',
                'payment_proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            ]);

        $response->assertSessionHasErrors('payment_proof');
        $this->assertDatabaseCount('complaints', 0);
    }

    /** 2. Valid payment proof image is accepted. 10. Existing creation behavior still works. */
    public function test_valid_payment_proof_is_accepted_and_complaint_created_normally(): void
    {
        $this->fakeOcrResult(['status' => 'not_found', 'transaction_id' => null]);

        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $response = $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Please refund my order.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $response->assertRedirect(route('complaints.create'));
        $response->assertSessionHas('status');

        $complaint = Complaint::first();
        $this->assertNotNull($complaint);
        $this->assertSame($customer->id, $complaint->user_id);
        $this->assertSame($reason->id, $complaint->reason_id);
        $this->assertSame('pending', $complaint->status);
        $this->assertNotNull($complaint->payment_proof_path);
        Storage::disk('local')->assertExists($complaint->payment_proof_path);
        $this->assertDatabaseHas('complaint_history', [
            'complaint_id' => $complaint->id,
            'action' => 'complaint_created',
        ]);
    }

    /**
     * 3 & 4. Complaint created and transaction ID stored when OCR succeeds.
     *
     * OCR now runs in ProcessPaymentProofOcr, a queued job (see
     * app/Jobs/ProcessPaymentProofOcr.php) - the flash message can no
     * longer promise a specific OCR outcome, since it's built before the
     * job has necessarily run. The job dispatch below still executes
     * synchronously and inline here because phpunit.xml sets
     * QUEUE_CONNECTION=sync for tests - a real deployment runs it on a
     * separate worker process instead (see README's Getting started and
     * Deployment sections).
     */
    public function test_complaint_created_with_extracted_transaction_id(): void
    {
        $this->fakeOcrResult(['status' => 'extracted', 'transaction_id' => '445566778899']);

        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Please refund my order.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])->assertSessionHas('status', fn ($status) => str_contains($status, 'being checked automatically'));

        $complaint = Complaint::first();
        $this->assertSame('extracted', $complaint->ocr_status);
        $this->assertSame('445566778899', $complaint->transaction_id);
    }

    /** 5. Complaint still created when OCR finds no transaction ID. */
    public function test_complaint_still_created_when_no_transaction_id_found(): void
    {
        $this->fakeOcrResult(['status' => 'not_found', 'transaction_id' => null]);

        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $response = $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Please refund my order.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $response->assertRedirect(route('complaints.create'));
        $complaint = Complaint::first();
        $this->assertNotNull($complaint);
        $this->assertSame('not_found', $complaint->ocr_status);
        $this->assertNull($complaint->transaction_id);
    }

    /** 6. Complaint still created when Tesseract/OCR fails outright. */
    public function test_complaint_still_created_when_ocr_fails(): void
    {
        $this->fakeOcrResult(['status' => 'failed', 'transaction_id' => null]);

        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $response = $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Please refund my order.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $response->assertRedirect(route('complaints.create'));
        $complaint = Complaint::first();
        $this->assertNotNull($complaint);
        $this->assertSame('failed', $complaint->ocr_status);
        $this->assertNull($complaint->transaction_id);
    }

    /**
     * The real PaymentProofOcrService (not mocked here) must not throw even
     * when Tesseract can't read the given path at all - this is the one
     * test in this file that exercises the service's own catch block
     * directly, without touching a real Tesseract binary either way (a
     * nonexistent path fails before the binary would even matter).
     */
    public function test_ocr_service_itself_never_throws_on_an_unreadable_image(): void
    {
        $result = (new PaymentProofOcrService)->process('/nonexistent/path/to/image.jpg');

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['transaction_id']);
    }

    /**
     * The controller must dispatch OCR as a job rather than run it inline -
     * Queue::fake() here so this test verifies the WIRING (dispatched, with
     * the right complaint) independently of ProcessPaymentProofOcr's own
     * behavior, which has its own dedicated tests
     * (tests/Feature/Jobs/ProcessPaymentProofOcrTest.php).
     */
    public function test_ocr_processing_is_dispatched_as_a_queued_job_on_successful_submission(): void
    {
        Queue::fake();

        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Please refund my order.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $complaint = Complaint::first();
        $this->assertNotNull($complaint);
        // Not resolved yet - the whole point of dispatching a job instead
        // of running OCR inline is that nothing here has processed it.
        $this->assertSame('pending', $complaint->ocr_status);

        Queue::assertPushed(ProcessPaymentProofOcr::class, fn ($job) => $job->complaint->is($complaint));
    }

    public function test_ocr_job_is_not_dispatched_when_submission_fails_validation(): void
    {
        Queue::fake();

        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Please refund my order.',
                // payment_proof omitted - validation fails before any
                // complaint (and therefore any job) is ever created.
            ]);

        Queue::assertNotPushed(ProcessPaymentProofOcr::class);
    }

    /** 9. Duplicate transaction ID is detected/flagged, not rejected. */
    public function test_duplicate_transaction_id_is_flagged_not_rejected(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->fakeOcrResult(['status' => 'extracted', 'transaction_id' => 'DUPLICATE123']);

        $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'First complaint.',
                'payment_proof' => UploadedFile::fake()->image('proof1.jpg'),
            ]);

        $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Second complaint, same transaction id.',
                'payment_proof' => UploadedFile::fake()->image('proof2.jpg'),
            ]);

        // Both complaints were created - a duplicate is never a rejection.
        $this->assertDatabaseCount('complaints', 2);

        [$first, $second] = Complaint::orderBy('id')->get();
        $this->assertTrue($first->hasDuplicateTransactionId());
        $this->assertTrue($second->hasDuplicateTransactionId());
    }

    public function test_unique_transaction_id_is_not_flagged_as_duplicate(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->fakeOcrResult(['status' => 'extracted', 'transaction_id' => 'UNIQUE999']);

        $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Only complaint.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $complaint = Complaint::first();
        $this->assertFalse($complaint->hasDuplicateTransactionId());
    }

    /**
     * Regression test: the DB row can reference a file that no longer
     * exists on disk (e.g. Render's free-tier local storage is wiped on
     * every restart) - this must 404 cleanly, not crash with an uncaught
     * Flysystem UnableToRetrieveMetadata exception.
     */
    public function test_downloading_a_missing_payment_proof_file_returns_404_not_a_crash(): void
    {
        $this->fakeOcrResult(['status' => 'not_found', 'transaction_id' => null]);

        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $this->idempotencyKey()])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'Proof will be deleted after upload.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $complaint = Complaint::first();
        Storage::disk('local')->delete($complaint->payment_proof_path);

        $this->actingAs($customer)
            ->get(route('complaints.payment-proof.download', $complaint))
            ->assertStatus(404);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['description' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'approved']);

        $this->actingAs($admin)
            ->get(route('admin.complaints.payment-proof.download', $complaint))
            ->assertStatus(404);
    }

    /**
     * Regression test: payment_proof_path is nullable at the DB level
     * specifically to accommodate complaints created before this feature
     * existed (a real migration failure on Postgres proved this is
     * necessary - see the migration file). Storage::exists() requires a
     * string, so passing it a null path would throw a TypeError (500), not
     * a clean 404, unless explicitly guarded against.
     */
    public function test_downloading_payment_proof_on_a_legacy_complaint_with_no_path_returns_404_not_a_crash(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();
        $complaint = Complaint::create([
            'user_id' => $customer->id,
            'reason_id' => $reason->id,
            'message' => 'Pre-existing complaint from before this feature.',
            'priority' => 'LOW',
            'status' => 'pending',
            'payment_proof_path' => null,
        ]);

        $this->actingAs($customer)
            ->get(route('complaints.payment-proof.download', $complaint))
            ->assertStatus(404);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['description' => 'admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'approved']);

        $this->actingAs($admin)
            ->get(route('admin.complaints.payment-proof.download', $complaint))
            ->assertStatus(404);
    }
}
