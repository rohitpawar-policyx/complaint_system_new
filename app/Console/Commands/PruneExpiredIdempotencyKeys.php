<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;

/**
 * Deletes idempotency_keys rows past their expires_at. Without this the
 * table grows forever - every complaint submission (successful or not)
 * leaves a row behind, and there's no other mechanism that ever removes
 * one. A simple scheduled command is sufficient here; this app has no
 * queue worker running (QUEUE_CONNECTION=sync) so a queued job would add
 * infrastructure this doesn't otherwise need.
 */
class PruneExpiredIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:prune';

    protected $description = 'Delete expired idempotency key records';

    public function handle(): int
    {
        $deleted = IdempotencyKey::where('expires_at', '<', now())->delete();

        $this->info("Deleted {$deleted} expired idempotency key record(s).");

        return self::SUCCESS;
    }
}
