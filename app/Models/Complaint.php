<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Complaint extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'in_progress', 'resolved', 'closed', 'rejected'];

    public const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH'];

    /** Statuses where an existing chat becomes read-only (history visible, no new messages). */
    public const CHAT_READ_ONLY_STATUSES = ['resolved', 'closed', 'rejected'];

    public const OCR_STATUSES = ['pending', 'extracted', 'not_found', 'failed'];

    protected $fillable = [
        'user_id', 'reason_id', 'message', 'priority', 'status', 'assigned_to',
        'payment_proof_path', 'transaction_id', 'ocr_status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ComplaintReason::class, 'reason_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ComplaintAttachment::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ComplaintHistory::class)->orderBy('created_at');
    }

    public function chatConversation(): HasOne
    {
        return $this->hasOne(ChatConversation::class);
    }

    /**
     * Whether this complaint's OCR-extracted transaction ID also appears on
     * a different complaint. Not a validation rule - transaction_id is
     * deliberately not unique (see the migration) - this only surfaces a
     * warning for an admin to actually investigate.
     */
    public function hasDuplicateTransactionId(): bool
    {
        if ($this->transaction_id === null) {
            return false;
        }

        return static::where('transaction_id', $this->transaction_id)
            ->where('id', '!=', $this->id)
            ->exists();
    }
}
