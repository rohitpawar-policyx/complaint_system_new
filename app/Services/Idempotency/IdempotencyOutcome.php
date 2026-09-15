<?php

namespace App\Services\Idempotency;

use App\Models\IdempotencyKey;

/**
 * The result of attempting to claim an idempotency key - a small,
 * explicit alternative to the middleware branching on magic strings or
 * catching control-flow exceptions for what is fundamentally a 4-way
 * decision (execute / replay / conflict / in-progress).
 */
final class IdempotencyOutcome
{
    public const EXECUTE = 'execute';

    public const REPLAY = 'replay';

    public const CONFLICT = 'conflict';

    public const IN_PROGRESS = 'in_progress';

    private function __construct(
        public readonly string $action,
        public readonly ?IdempotencyKey $record = null,
    ) {}

    /** This request owns the key and must execute the wrapped handler. */
    public static function execute(IdempotencyKey $record): self
    {
        return new self(self::EXECUTE, $record);
    }

    /** An identical request already completed - replay its stored response. */
    public static function replay(IdempotencyKey $record): self
    {
        return new self(self::REPLAY, $record);
    }

    /** Same key, but the request itself is different - 409, do not execute. */
    public static function conflict(): self
    {
        return new self(self::CONFLICT);
    }

    /** Another request with this exact key is currently executing - 409, do not execute. */
    public static function inProgress(): self
    {
        return new self(self::IN_PROGRESS);
    }
}
