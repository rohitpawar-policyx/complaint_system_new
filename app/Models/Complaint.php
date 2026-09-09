<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'in_progress', 'resolved', 'closed', 'rejected'];
    public const PRIORITIES = ['LOW', 'MEDIUM', 'HIGH'];

    protected $fillable = ['user_id', 'reason_id', 'message', 'priority', 'status', 'assigned_to'];

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
}
