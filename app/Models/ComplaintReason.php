<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintReason extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'priority', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'reason_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
