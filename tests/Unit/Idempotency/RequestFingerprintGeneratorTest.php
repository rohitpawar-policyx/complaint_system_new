<?php

namespace Tests\Unit\Idempotency;

use App\Services\Idempotency\RequestFingerprintGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RequestFingerprintGeneratorTest extends TestCase
{
    private RequestFingerprintGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new RequestFingerprintGenerator;
    }

    private function makeRequest(array $fields = [], array $files = []): Request
    {
        return Request::create('/complaints', 'POST', $fields, [], $files);
    }

    public function test_identical_field_only_requests_produce_the_same_fingerprint(): void
    {
        $a = $this->makeRequest(['reason_id' => '1', 'message' => 'Please help']);
        $b = $this->makeRequest(['reason_id' => '1', 'message' => 'Please help']);

        $this->assertSame($this->generator->generate($a), $this->generator->generate($b));
    }

    public function test_different_field_values_produce_different_fingerprints(): void
    {
        $a = $this->makeRequest(['reason_id' => '1', 'message' => 'Please help']);
        $b = $this->makeRequest(['reason_id' => '1', 'message' => 'Please help me urgently']);

        $this->assertNotSame($this->generator->generate($a), $this->generator->generate($b));
    }

    public function test_field_submission_order_does_not_affect_the_fingerprint(): void
    {
        $a = $this->makeRequest(['reason_id' => '1', 'message' => 'x']);
        $b = $this->makeRequest(['message' => 'x', 'reason_id' => '1']);

        $this->assertSame($this->generator->generate($a), $this->generator->generate($b));
    }

    public function test_the_idempotency_key_header_field_itself_is_excluded_from_the_fingerprint(): void
    {
        // Not a header in this test (Request::create() input, not header) -
        // simulates a client that (incorrectly) also puts it in the body;
        // the fingerprint must not change based on its value either way.
        $a = $this->makeRequest(['reason_id' => '1', 'idempotency_key' => 'abc-123']);
        $b = $this->makeRequest(['reason_id' => '1', 'idempotency_key' => 'completely-different']);

        $this->assertSame($this->generator->generate($a), $this->generator->generate($b));
    }

    public function test_csrf_token_and_method_spoofing_fields_are_excluded(): void
    {
        $a = $this->makeRequest(['reason_id' => '1', '_token' => 'aaa', '_method' => 'PUT']);
        $b = $this->makeRequest(['reason_id' => '1', '_token' => 'bbb', '_method' => 'PATCH']);

        $this->assertSame($this->generator->generate($a), $this->generator->generate($b));
    }

    public function test_identical_file_content_produces_the_same_fingerprint_regardless_of_temp_path(): void
    {
        // Two separate UploadedFile::fake() calls allocate two DIFFERENT
        // temp file paths on disk, even with byte-identical content - this
        // is exactly what happens across two real HTTP requests re-uploading
        // "the same" file. The fingerprint must depend on the bytes, not
        // the path.
        $fileA = UploadedFile::fake()->create('proof.jpg', 50, 'image/jpeg');
        $fileB = UploadedFile::fake()->create('proof.jpg', 50, 'image/jpeg');
        // Force identical content (fake() content is otherwise random padding).
        file_put_contents($fileA->getRealPath(), 'identical-bytes');
        file_put_contents($fileB->getRealPath(), 'identical-bytes');

        $this->assertNotSame($fileA->getRealPath(), $fileB->getRealPath());

        $a = $this->makeRequest(['reason_id' => '1'], ['payment_proof' => $fileA]);
        $b = $this->makeRequest(['reason_id' => '1'], ['payment_proof' => $fileB]);

        $this->assertSame($this->generator->generate($a), $this->generator->generate($b));
    }

    public function test_different_file_content_produces_a_different_fingerprint(): void
    {
        $fileA = UploadedFile::fake()->create('proof.jpg', 50, 'image/jpeg');
        $fileB = UploadedFile::fake()->create('proof.jpg', 50, 'image/jpeg');
        file_put_contents($fileA->getRealPath(), 'content-one');
        file_put_contents($fileB->getRealPath(), 'content-two');

        $a = $this->makeRequest(['reason_id' => '1'], ['payment_proof' => $fileA]);
        $b = $this->makeRequest(['reason_id' => '1'], ['payment_proof' => $fileB]);

        $this->assertNotSame($this->generator->generate($a), $this->generator->generate($b));
    }

    public function test_multiple_attachment_files_in_a_different_array_order_still_fingerprint_the_same(): void
    {
        $fileA = UploadedFile::fake()->create('a.jpg', 10);
        $fileB = UploadedFile::fake()->create('b.jpg', 10);
        file_put_contents($fileA->getRealPath(), 'aaa');
        file_put_contents($fileB->getRealPath(), 'bbb');

        $request1 = $this->makeRequest([], ['attachments' => [$fileA, $fileB]]);
        $request2 = $this->makeRequest([], ['attachments' => [$fileB, $fileA]]);

        $this->assertSame($this->generator->generate($request1), $this->generator->generate($request2));
    }

    public function test_generate_is_deterministic_across_repeated_calls_on_the_same_request(): void
    {
        $request = $this->makeRequest(['reason_id' => '1', 'message' => 'x']);

        $this->assertSame($this->generator->generate($request), $this->generator->generate($request));
    }
}
