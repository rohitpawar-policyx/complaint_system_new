<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintAttachment extends Model
{
    use HasFactory;

    // This table has created_at only, no updated_at column.
    const UPDATED_AT = null;

    protected $fillable = ['complaint_id', 'original_name', 'stored_name', 'file_path', 'mime_type', 'file_size'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
