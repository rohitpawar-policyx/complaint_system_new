<?php

namespace App\Services\Idempotency;

use App\Models\IdempotencyKey;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Orchestrates the idempotency lifecycle: atomically claiming a key,
 * replaying a completed request's response, and detecting conflicting or
 * concurrently-processing requests. Deliberately endpoint-agnostic (no
 * knowledge of "complaint" anywhere here) so the same manager backs any
 * route the EnsureIdempotentRequest middleware is attached to.
 */
class IdempotencyManager
{
    /** How long a completed/failed record is kept before PruneExpiredIdempotencyKeys removes it. */
    public const DEFAULT_TTL_HOURS = 24;

    /**
     * Attempts to atomically claim (owner_key, idempotency_key, endpoint).
     *
     * Race safety comes from the database unique constraint, not from an
     * exists()-then-create() check: two concurrent requests both attempt
     * the INSERT, the constraint allows exactly one of them to succeed,
     * and the other catches the resulting QueryException and looks up
     * whatever the winner wrote - there is no window where both can
     * believe they are first.
     */
    public function attemptClaim(Request $request, string $key, string $endpoint, string $hash): IdempotencyOutcome
    {
        [$ownerKey, $userId] = $this->resolveOwner($request);

        try {
            $record = IdempotencyKey::create([
                'owner_key' => $ownerKey,
                'user_id' => $userId,
                'idempotency_key' => $key,
                'endpoint' => $endpoint,
                'request_hash' => $hash,
                'status' => IdempotencyKey::STATUS_PROCESSING,
                'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
            ]);

            return IdempotencyOutcome::execute($record);
        } catch (QueryException $e) {
            if (! $this->isUniqueConstraintViolation($e)) {
                throw $e;
            }
        }

        $existing = IdempotencyKey::where('owner_key', $ownerKey)
            ->where('idempotency_key', $key)
            ->where('endpoint', $endpoint)
            ->first();

        if ($existing === null) {
            // Vanishingly unlikely: deleted between our failed insert and
            // this lookup (e.g. by the cleanup command mid-request). Safe
            // to ask the caller to just retry rather than guessing.
            return IdempotencyOutcome::inProgress();
        }

        // An expired record's stored hash/status/response are no longer
        // meaningful - the whole point of expiry is that this key is no
        // longer bound to that outcome. Checked before the hash/status
        // checks below (an expired row must never be replayed, and must
        // never produce a 409 against a "different" request either - it's
        // simply not in effect anymore), and reuses the same row (rather
        // than delete-then-insert, which would just reopen the same race
        // this whole claim step exists to close) via a conditional UPDATE.
        if ($existing->expires_at->isPast()) {
            return $this->reclaimExpired($existing, $hash);
        }

        if (! $existing->matchesHash($hash)) {
            return IdempotencyOutcome::conflict();
        }

        if ($existing->isCompleted()) {
            return IdempotencyOutcome::replay($existing);
        }

        if ($existing->status === IdempotencyKey::STATUS_FAILED) {
            return $this->reclaim($existing, IdempotencyKey::STATUS_FAILED);
        }

        // status === processing
        if ($existing->isStaleProcessing()) {
            return $this->reclaimStaleProcessing($existing);
        }

        return IdempotencyOutcome::inProgress();
    }

    /**
     * Resets an expired row in place so it can be claimed by a genuinely
     * new logical request - including the NEW hash, since the expired
     * row's old hash no longer constrains anything. Conditional on
     * expires_at still being in the past at write time, same pattern as
     * every other reclaim, so two requests racing to reclaim the same
     * expired key can't both win.
     */
    private function reclaimExpired(IdempotencyKey $existing, string $newHash): IdempotencyOutcome
    {
        $affected = IdempotencyKey::where('id', $existing->id)
            ->where('expires_at', '<', now())
            ->update([
                'request_hash' => $newHash,
                'status' => IdempotencyKey::STATUS_PROCESSING,
                'response_status' => null,
                'response_body' => null,
                'resource_type' => null,
                'resource_id' => null,
                'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
            ]);

        if ($affected === 1) {
            return IdempotencyOutcome::execute($existing->fresh());
        }

        return IdempotencyOutcome::inProgress();
    }

    /**
     * Reclaims a failed row so its key can be retried. Uses a conditional
     * UPDATE (not a blind save()) and checks the affected-row count, so two
     * concurrent retries of the same failed key can't both believe they
     * won the reclaim.
     */
    private function reclaim(IdempotencyKey $existing, string $fromStatus): IdempotencyOutcome
    {
        $affected = IdempotencyKey::where('id', $existing->id)
            ->where('status', $fromStatus)
            ->update(['status' => IdempotencyKey::STATUS_PROCESSING]);

        if ($affected === 1) {
            return IdempotencyOutcome::execute($existing->fresh());
        }

        // Someone else reclaimed it first - from this request's point of
        // view that's indistinguishable from "still processing".
        return IdempotencyOutcome::inProgress();
    }

    /**
     * Same conditional-update pattern as reclaim(), plus the staleness
     * check in the WHERE clause itself so a row that stopped being stale
     * between our read and this write can't be reclaimed twice.
     */
    private function reclaimStaleProcessing(IdempotencyKey $existing): IdempotencyOutcome
    {
        $staleBefore = now()->subMinutes(IdempotencyKey::STALE_PROCESSING_MINUTES);

        $affected = IdempotencyKey::where('id', $existing->id)
            ->where('status', IdempotencyKey::STATUS_PROCESSING)
            ->where('updated_at', '<', $staleBefore)
            ->update(['status' => IdempotencyKey::STATUS_PROCESSING, 'updated_at' => now()]);

        if ($affected === 1) {
            return IdempotencyOutcome::execute($existing->fresh());
        }

        return IdempotencyOutcome::inProgress();
    }

    /**
     * Marks a record completed and stores enough to replay it later.
     * Called by the middleware strictly AFTER $next($request) has already
     * returned - which means any DB transaction the wrapped controller
     * opened has already committed or rolled back by this point. Calling
     * this before that would risk marking "completed" a request whose
     * transaction later fails.
     */
    public function complete(IdempotencyKey $record, Request $request, SymfonyResponse $response, ?string $resourceType, mixed $resourceId): void
    {
        $record->update([
            'status' => IdempotencyKey::STATUS_COMPLETED,
            'response_status' => $response->getStatusCode(),
            'response_body' => $this->describeResponse($request, $response),
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ]);
    }

    public function fail(IdempotencyKey $record): void
    {
        $record->update(['status' => IdempotencyKey::STATUS_FAILED]);
    }

    /**
     * Reconstructs the original response from its stored description.
     * Handles both shapes this app can produce: a redirect-with-flash (the
     * only shape ComplaintController's web-form endpoint actually uses)
     * and a JSON body (for a future API-style endpoint reusing this same
     * middleware) - see describeResponse().
     */
    public function buildReplayResponse(IdempotencyKey $record): SymfonyResponse
    {
        $body = $record->response_body ?? [];

        if (($body['type'] ?? null) === 'redirect') {
            $redirect = redirect()->to($body['target']);
            foreach (($body['flash'] ?? []) as $flashKey => $value) {
                $redirect->with($flashKey, $value);
            }

            return $redirect;
        }

        if (($body['type'] ?? null) === 'json') {
            return response()->json($body['body'] ?? [], $record->response_status ?? 200);
        }

        return response()->noContent($record->response_status ?? 204);
    }

    /**
     * Same Idempotency-Key, but a different request. Deliberately NOT a
     * raw 409 for a normal browser form submission: every other validation
     * failure in this app redirects back with a flashed error (302), and a
     * literal 409 status on a RedirectResponse would stop the browser from
     * actually following it, breaking that UX for no benefit. JSON/API
     * clients (a future POST /payments, say) get the literal 409 - see the
     * class-level note on this being a deliberate, explained deviation
     * from a status code that only makes sense for JSON callers.
     */
    public function buildConflictResponse(Request $request): SymfonyResponse
    {
        return $this->errorResponse(
            $request,
            'The Idempotency-Key has already been used with a different request.',
            409
        );
    }

    public function buildInProgressResponse(Request $request): SymfonyResponse
    {
        return $this->errorResponse(
            $request,
            'This request is already being processed. Please wait a moment before retrying.',
            409
        );
    }

    public function buildMissingKeyResponse(Request $request): SymfonyResponse
    {
        return $this->errorResponse($request, 'The Idempotency-Key header is required.', 422);
    }

    public function buildInvalidKeyResponse(Request $request): SymfonyResponse
    {
        return $this->errorResponse($request, 'The Idempotency-Key header is invalid.', 422);
    }

    private function errorResponse(Request $request, string $message, int $jsonStatus): SymfonyResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $jsonStatus);
        }

        return redirect()->back()->withInput()->withErrors(['idempotency_key' => $message]);
    }

    /**
     * @return array{owner_key: string, 1: int|null}
     */
    private function resolveOwner(Request $request): array
    {
        $user = $request->user() ?? Auth::user();

        if ($user !== null) {
            return ["user:{$user->id}", $user->id];
        }

        // Guest fallback for a future guest-accessible endpoint - the
        // session ID is stable across retries from the same browser
        // session without requiring authentication. This endpoint is
        // currently auth-only, so this branch isn't exercised today, but
        // the manager is designed to be reused for one that isn't.
        return ['guest:'.$request->session()->getId(), null];
    }

    /**
     * @return array{type: string, target?: string, flash?: array, body?: mixed}
     */
    private function describeResponse(Request $request, SymfonyResponse $response): array
    {
        if ($response instanceof RedirectResponse) {
            return [
                'type' => 'redirect',
                'target' => $response->getTargetUrl(),
                'flash' => $this->freshFlashData($request),
            ];
        }

        if ($response instanceof JsonResponse) {
            return [
                'type' => 'json',
                'body' => $response->getData(true),
            ];
        }

        return ['type' => 'raw'];
    }

    /**
     * Only the session keys flashed during THIS request cycle (Laravel
     * tracks these under the "_flash.new" bag) - not the whole session,
     * which would include unrelated data with no business being stored
     * here.
     */
    private function freshFlashData(Request $request): array
    {
        $freshKeys = $request->session()->get('_flash.new', []);
        $flash = [];

        foreach ($freshKeys as $flashKey) {
            $flash[$flashKey] = $request->session()->get($flashKey);
        }

        return $flash;
    }

    /**
     * SQLSTATE class 23 = integrity constraint violation, portable across
     * drivers: MySQL reports the general '23000', Postgres reports the
     * more specific '23505' (unique_violation) - checking both rather than
     * parsing driver-specific error message text.
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
