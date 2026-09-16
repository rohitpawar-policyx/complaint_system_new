<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Services\Idempotency\IdempotencyManager;
use App\Services\Idempotency\IdempotencyOutcome;
use App\Services\Idempotency\RequestFingerprintGenerator;
use App\Support\Idempotency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a state-changing POST endpoint safe to retry: the same
 * Idempotency-Key + the same request body/files always produces exactly
 * one execution, no matter how many times it's sent (double-click, network
 * retry, reverse-proxy retry, ...).
 *
 * Deliberately generic - see RequestFingerprintGenerator and
 * IdempotencyManager for why this has no knowledge of "complaint" or any
 * other endpoint-specific concept. Attach it to any state-changing route
 * that should be retry-safe; the only thing that route's controller needs
 * to do differently is call App\Support\Idempotency::recordResource()
 * after its own successful DB commit (see ComplaintController::store()).
 */
class EnsureIdempotentRequest
{
    private const HEADER = 'Idempotency-Key';

    /**
     * Fallback source for a plain HTML <form> POST (e.g. complaints.store):
     * a browser form submission cannot set a custom HTTP header at all, so
     * such routes send the key as a hidden input field with this name
     * instead. The header takes priority for any client that can set one
     * (a JSON API caller); this is purely a fallback, not a replacement.
     * RequestFingerprintGenerator::EXCLUDED_FIELDS already excludes this
     * same field name from the request hash.
     */
    private const INPUT_FIELD = 'idempotency_key';

    private const MAX_KEY_LENGTH = 255;

    public function __construct(
        private IdempotencyManager $manager,
        private RequestFingerprintGenerator $fingerprinter,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER) ?? $request->input(self::INPUT_FIELD);

        if ($key === null || trim($key) === '') {
            return $this->manager->buildMissingKeyResponse($request);
        }

        if (mb_strlen($key) > self::MAX_KEY_LENGTH) {
            return $this->manager->buildInvalidKeyResponse($request);
        }

        // Route name when available (e.g. "complaints.store"); falls back
        // to the path for an unnamed route so the same key still can't
        // collide across two different endpoints.
        $endpoint = $request->route()?->getName() ?? $request->path();

        // Computed once, outside any try/catch, before any DB write - a
        // hashing failure (e.g. an unreadable upload) should surface as a
        // normal error, not as a half-claimed idempotency row.
        $hash = $this->fingerprinter->generate($request);

        $outcome = $this->manager->attemptClaim($request, $key, $endpoint, $hash);

        return match ($outcome->action) {
            IdempotencyOutcome::REPLAY => $this->manager->buildReplayResponse($outcome->record),
            IdempotencyOutcome::CONFLICT => $this->manager->buildConflictResponse($request),
            IdempotencyOutcome::IN_PROGRESS => $this->manager->buildInProgressResponse($request),
            IdempotencyOutcome::EXECUTE => $this->execute($request, $next, $outcome->record),
        };
    }

    private function execute(Request $request, Closure $next, IdempotencyKey $record): Response
    {
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // The wrapped handler threw - nothing it was supposed to
            // create can have committed (or if it did, that's a bug in the
            // handler's own transaction boundaries, not something this
            // middleware can fix). Mark failed so the SAME key can be
            // retried, rather than permanently stuck.
            $this->manager->fail($record);

            throw $e;
        }

        $resource = Idempotency::resourceFrom($request);

        if ($resource === null) {
            // The handler returned normally but never signalled success
            // (e.g. ComplaintController's own validation failed and it
            // returned back()->withErrors(...) - also a 302, indistinguishable
            // from success by status code alone, which is exactly why that
            // explicit signal exists). Mark failed, not completed: this
            // response must never be replayed as if it were the success
            // response, and the key must remain retryable.
            $this->manager->fail($record);

            return $response;
        }

        $this->manager->complete($record, $request, $response, $resource['type'], $resource['id']);

        return $response;
    }
}
