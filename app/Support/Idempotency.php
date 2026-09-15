<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The one-line hook a controller calls after successfully creating the
 * resource an idempotent endpoint is protecting - nothing more.
 *
 * Why this exists: ComplaintController::store() (like most controllers in
 * this app) returns a plain redirect on both success AND on a handled
 * business/validation failure - both are a 302, so EnsureIdempotentRequest
 * cannot tell them apart from the HTTP response alone. This call is the
 * explicit signal: "the thing this endpoint exists to create was actually
 * created, and its DB transaction has already committed by the time you
 * read this" (it must only be called after the transaction commits, never
 * from inside it - see IdempotencyManager::complete()'s docblock for why).
 *
 * Deliberately just a request attribute, not a database write and not a
 * dependency on IdempotencyManager - a controller that calls this has no
 * need to know idempotency is involved at all, which is what keeps this
 * reusable for a future PaymentController/RegistrationController.
 */
class Idempotency
{
    private const REQUEST_ATTRIBUTE = 'idempotency.resource';

    public static function recordResource(Request $request, string $resourceType, mixed $resourceId): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, [
            'type' => $resourceType,
            'id' => $resourceId,
        ]);
    }

    /**
     * @return array{type: string, id: mixed}|null
     */
    public static function resourceFrom(Request $request): ?array
    {
        return $request->attributes->get(self::REQUEST_ATTRIBUTE);
    }
}
