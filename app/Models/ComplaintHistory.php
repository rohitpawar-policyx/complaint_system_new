<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintHistory extends Model
{
    use HasFactory;

    // Eloquent would otherwise guess "complaint_histories" (pluralized);
    // the actual table is named complaint_history (matches the original schema).
    protected $table = 'complaint_history';

    // This table has created_at only, no updated_at column.
    const UPDATED_AT = null;

    protected $fillable = [
        'complaint_id', 'action', 'old_status', 'new_status',
        'assigned_from', 'assigned_to', 'performed_by', 'description',
    ];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function assignedFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_from');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
