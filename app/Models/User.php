<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const STATUSES = ['pending', 'approved', 'blocked'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'role_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Complaints this user submitted. */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    /** Complaints this user is currently assigned to handle. */
    public function assignedComplaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'assigned_to');
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Laravel's built-in auth guard only checks credentials/existence; this
     * app additionally requires an approved account to authenticate,
     * mirroring the previous require_authenticated() status check.
     */
    public function canAuthenticate(): bool
    {
        return $this->isApproved();
    }
}
