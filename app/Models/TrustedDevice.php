<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_uuid',
        'device_name',
        'ip_address',
        'status',
        'enrollment_token',
        'enrollment_token_expires_at',
        'verification_code',
        'verification_code_expires_at',
        'verification_attempts',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'enrollment_token_expires_at' => 'datetime',
            'verification_code_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'verification_attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }
}
