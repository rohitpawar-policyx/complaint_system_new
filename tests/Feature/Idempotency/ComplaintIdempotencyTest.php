<?php

namespace Tests\Feature\Idempotency;

use App\Models\Complaint;
use App\Models\ComplaintReason;
use App\Models\IdempotencyKey;
use App\Models\Role;
use App\Models\User;
use App\Services\Idempotency\IdempotencyManager;
use App\Services\PaymentProofOcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->fakeOcrResult(['status' => 'not_found', 'transaction_id' => null]);
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

    private function fakeOcrResult(array $result): void
    {
        $mock = \Mockery::mock(PaymentProofOcrService::class);
        $mock->shouldReceive('process')->andReturn($result);
        $this->app->instance(PaymentProofOcrService::class, $mock);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function submit(User $customer, ComplaintReason $reason, string $key, array $overrides = [])
    {
        return $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => $key])
            ->post(route('complaints.store'), array_merge([
                'reason_id' => $reason->id,
                'message' => 'Please refund my order.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ], $overrides));
    }

    /** 1. First request creates exactly one complaint. */
    public function test_first_request_creates_exactly_one_complaint(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $response = $this->submit($customer, $reason, 'key-001');

        $response->assertRedirect(route('complaints.create'));
        $this->assertDatabaseCount('complaints', 1);

        $record = IdempotencyKey::first();
        $this->assertNotNull($record);
        $this->assertSame(IdempotencyKey::STATUS_COMPLETED, $record->status);
        $this->assertSame('complaint', $record->resource_type);
        $this->assertSame(Complaint::first()->id, $record->resource_id);
    }

    /**
     * 2. Same Idempotency-Key + same request: only one complaint exists,
     * second request does not create another, and receives the same
     * logical result (the same flashed success message, replaying the
     * original response rather than re-running complaint creation).
     */
    public function test_retry_with_same_key_and_same_request_does_not_create_a_second_complaint(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        // Deliberately the SAME UploadedFile instance passed to both calls,
        // so both requests carry byte-identical payment proof content -
        // the actual "double-click retry" scenario.
        $file = UploadedFile::fake()->image('proof.jpg');

        $first = $this->submit($customer, $reason, 'key-002', ['payment_proof' => $file]);
        $second = $this->submit($customer, $reason, 'key-002', ['payment_proof' => $file]);

        $this->assertDatabaseCount('complaints', 1);
        $first->assertSessionHas('status');
        $second->assertSessionHas('status', $first->getSession()->get('status'));
        $second->assertRedirect(route('complaints.create'));
    }

    /**
     * 3. Same Idempotency-Key + different complaint data: no second
     * complaint, conflict is surfaced. For this app's web/redirect-based
     * flow that means a redirect back with a flashed error - the SAME
     * shape as every other validation failure - not a literal 409 status,
     * which would stop a real browser from following the redirect at all
     * (see IdempotencyManager::buildConflictResponse()'s docblock for why
     * that's a deliberate choice, not an oversight). A JSON-expecting
     * client (e.g. a future API endpoint reusing this same middleware)
     * DOES get the literal 409 - proven separately below.
     */
    public function test_same_key_with_different_message_is_rejected_and_creates_no_second_complaint(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->submit($customer, $reason, 'key-003', ['message' => 'First message.']);
        $second = $this->submit($customer, $reason, 'key-003', ['message' => 'A completely different message.']);

        $second->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('complaints', 1);
    }

    /** 4. Same Idempotency-Key + different payment proof: no second complaint, conflict rejected. */
    public function test_same_key_with_different_payment_proof_is_rejected_and_creates_no_second_complaint(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $fileA = UploadedFile::fake()->image('proof-a.jpg');
        $fileB = UploadedFile::fake()->image('proof-b.jpg');
        file_put_contents($fileA->getRealPath(), 'aaaaaaaaaa');
        file_put_contents($fileB->getRealPath(), 'bbbbbbbbbb');

        $this->submit($customer, $reason, 'key-004', ['payment_proof' => $fileA]);
        $second = $this->submit($customer, $reason, 'key-004', ['payment_proof' => $fileB]);

        $second->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('complaints', 1);
    }

    /**
     * Same conflict, but from a JSON-expecting client (Accept:
     * application/json) - proves the literal 409 status the spec calls
     * for is genuinely implemented, just routed to the caller type it
     * actually makes sense for.
     */
    public function test_same_key_with_different_request_returns_a_literal_409_for_json_clients(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => 'key-003b', 'Accept' => 'application/json'])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'First message.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $second = $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => 'key-003b', 'Accept' => 'application/json'])
            ->post(route('complaints.store'), [
                'reason_id' => $reason->id,
                'message' => 'A different message.',
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $second->assertStatus(409);
        $second->assertJson(['message' => 'The Idempotency-Key has already been used with a different request.']);
        $this->assertDatabaseCount('complaints', 1);
    }

    /**
     * 5. Two simultaneous requests with the same key: only one complaint
     * is created. True parallel HTTP requests can't be produced inside a
     * single synchronous PHPUnit process, so this proves the actual
     * mechanism directly at the point where the race is won or lost: the
     * database unique constraint. Calling attemptClaim() twice in a row
     * with an identical hash IS exactly what happens at the database level
     * when two requests race - the first INSERT succeeds, the second hits
     * the unique constraint - regardless of whether the two calls happen
     * from one process or two.
     */
    public function test_concurrent_claims_with_the_same_key_only_let_one_through(): void
    {
        $customer = $this->makeCustomer();
        $request = \Illuminate\Http\Request::create('/complaints', 'POST', ['reason_id' => '1']);
        $request->setUserResolver(fn () => $customer);
        $request->setLaravelSession(app('session.store'));

        $manager = app(IdempotencyManager::class);

        $first = $manager->attemptClaim($request, 'key-005', 'complaints.store', 'same-hash');
        $second = $manager->attemptClaim($request, 'key-005', 'complaints.store', 'same-hash');

        $this->assertSame('execute', $first->action);
        $this->assertSame('in_progress', $second->action);
        $this->assertDatabaseCount('idempotency_keys', 1);
    }

    /**
     * 6. Request fails during complaint creation: complaint is not
     * partially created, idempotency state does not permanently block
     * retry, and the retry can succeed. Uses the controller's own
     * "reason became inactive" failure path (a real, already-existing
     * failure mode) to force ComplaintController::store() to return
     * without ever calling Idempotency::recordResource() - exactly the
     * "handler ran but didn't signal success" case the middleware must
     * treat as a failure, not a success to replay.
     */
    public function test_failed_request_does_not_permanently_block_retry_with_the_same_key(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();
        $reason->update(['active' => false]);

        $failed = $this->submit($customer, $reason, 'key-006');

        $failed->assertSessionHasErrors('reason_id');
        $this->assertDatabaseCount('complaints', 0);
        $this->assertSame(IdempotencyKey::STATUS_FAILED, IdempotencyKey::first()->status);

        // Retry with the SAME key, now that the underlying problem is fixed.
        $reason->update(['active' => true]);
        $retry = $this->submit($customer, $reason, 'key-006');

        $retry->assertRedirect(route('complaints.create'));
        $this->assertDatabaseCount('complaints', 1);
        $this->assertSame(IdempotencyKey::STATUS_COMPLETED, IdempotencyKey::first()->status);
    }

    /**
     * Same failure/retry guarantee, but for the handler actually throwing
     * (not just returning an unsuccessful response) - the middleware's
     * try/catch around $next($request) must mark the record failed and
     * let the exception continue propagating normally, not swallow it.
     */
    public function test_an_exception_during_complaint_creation_marks_the_key_failed_and_allows_retry(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $throwing = \Mockery::mock(PaymentProofOcrService::class);
        $throwing->shouldReceive('process')->andThrow(new \RuntimeException('Simulated OCR service crash.'));
        $this->app->instance(PaymentProofOcrService::class, $throwing);

        // Without this, Laravel's test-mode exception handler converts the
        // thrown RuntimeException into an ordinary 500 response instead of
        // letting it propagate - which would hide the exact thing this test
        // exists to prove: that EnsureIdempotentRequest's try/catch marks
        // the record failed and rethrows, rather than swallowing it.
        $this->withoutExceptionHandling();
        $this->expectException(\RuntimeException::class);

        try {
            $this->submit($customer, $reason, 'key-006b');
        } finally {
            $this->assertDatabaseCount('complaints', 0);
            $this->assertSame(IdempotencyKey::STATUS_FAILED, IdempotencyKey::first()->status);
        }
    }

    /** 7. Missing Idempotency-Key: rejected according to the app's validation conventions (redirect back with a flashed error). */
    public function test_missing_idempotency_key_header_is_rejected(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $response = $this->actingAs($customer)->post(route('complaints.store'), [
            'reason_id' => $reason->id,
            'message' => 'x',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ]);

        $response->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('complaints', 0);
    }

    /** 8. Empty/invalid/oversized Idempotency-Key: rejected. */
    public function test_empty_idempotency_key_is_rejected(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->submit($customer, $reason, '')->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('complaints', 0);
    }

    public function test_oversized_idempotency_key_is_rejected(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->submit($customer, $reason, str_repeat('a', 300))->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('complaints', 0);
    }

    /** 9. Different users using the same Idempotency-Key: requests remain isolated. */
    public function test_two_different_users_with_the_same_key_are_fully_isolated(): void
    {
        $userA = $this->makeCustomer();
        $userB = $this->makeCustomer();
        $reason = $this->makeReason();

        $responseA = $this->submit($userA, $reason, 'shared-key', ['message' => "User A's complaint."]);
        $responseB = $this->submit($userB, $reason, 'shared-key', ['message' => "User B's complaint."]);

        // Both succeeded independently - user B's differing owner scope
        // means this was never treated as a conflict or a replay of A's
        // result.
        $responseA->assertRedirect(route('complaints.create'));
        $responseB->assertRedirect(route('complaints.create'));
        $this->assertDatabaseCount('complaints', 2);

        $complaintA = Complaint::where('user_id', $userA->id)->first();
        $complaintB = Complaint::where('user_id', $userB->id)->first();
        $this->assertSame("User A's complaint.", $complaintA->message);
        $this->assertSame("User B's complaint.", $complaintB->message);

        // User B can never be handed user A's stored resource/response.
        $recordA = IdempotencyKey::where('user_id', $userA->id)->first();
        $recordB = IdempotencyKey::where('user_id', $userB->id)->first();
        $this->assertNotSame($recordA->id, $recordB->id);
        $this->assertSame($complaintA->id, $recordA->resource_id);
        $this->assertSame($complaintB->id, $recordB->resource_id);
    }

    /** 10. Expired idempotency key: treated as a fresh request, not replayed. */
    public function test_expired_idempotency_key_is_treated_as_a_new_request_not_replayed(): void
    {
        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->submit($customer, $reason, 'key-010', ['message' => 'Original message.']);
        $this->assertDatabaseCount('complaints', 1);

        // Simulate the key having expired since the original request.
        IdempotencyKey::query()->update(['expires_at' => now()->subDay()]);

        // A retry with the SAME key but genuinely different data (proving
        // it's treated as fresh, not a 409 against the old, expired hash).
        $retry = $this->submit($customer, $reason, 'key-010', ['message' => 'Completely different message.']);

        $retry->assertRedirect(route('complaints.create'));
        $this->assertDatabaseCount('complaints', 2);
        $this->assertDatabaseCount('idempotency_keys', 1); // reused the same row, not a second one
    }

    /** 11 & 12. Existing payment-proof/OCR/complaint-creation behavior is unaffected when used correctly (see also ComplaintPaymentProofTest, updated to send the now-required header). */
    public function test_extracted_transaction_id_and_ocr_status_still_populate_correctly_through_the_idempotent_endpoint(): void
    {
        $this->fakeOcrResult(['status' => 'extracted', 'transaction_id' => '445511667788']);

        $customer = $this->makeCustomer();
        $reason = $this->makeReason();

        $this->submit($customer, $reason, 'key-012');

        $complaint = Complaint::first();
        $this->assertSame('extracted', $complaint->ocr_status);
        $this->assertSame('445511667788', $complaint->transaction_id);
    }
}
