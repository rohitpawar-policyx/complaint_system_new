<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** How stale a "processing" row must be before a new request may reclaim it (crashed/timed-out process). */
    public const STALE_PROCESSING_MINUTES = 2;

    protected $fillable = [
        'owner_key', 'user_id', 'idempotency_key', 'endpoint', 'request_hash',
        'status', 'response_status', 'response_body', 'resource_type', 'resource_id', 'expires_at',
    ];

    protected $casts = [
        'response_body' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isStaleProcessing(): bool
    {
        return $this->isProcessing()
            && $this->updated_at->lt(now()->subMinutes(self::STALE_PROCESSING_MINUTES));
    }

    public function matchesHash(string $hash): bool
    {
        return hash_equals($this->request_hash, $hash);
    }
}
