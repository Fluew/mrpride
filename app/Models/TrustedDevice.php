<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrustedDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_uuid',
        'device_name',
        'ip_address',
        'status',
        'credential_hash',
        'enrollment_code',
        'enrollment_code_expires_at',
        'is_shared',
        'authorized_user_ids',
        'approved_by',
        'approved_at',
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
            'is_shared' => 'boolean',
            'authorized_user_ids' => 'array',
            'enrollment_code_expires_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'active'], true);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    /**
     * Check if a specific user account is authorized to use this workstation.
     */
    public function isAuthorizedForUser(User $user): bool
    {
        if (! $this->isApproved()) {
            return false;
        }

        if ($this->is_shared) {
            $authorized = is_array($this->authorized_user_ids)
                ? array_map('intval', $this->authorized_user_ids)
                : [];

            return in_array((int) $user->id, $authorized, true) || ($this->user_id && (int) $this->user_id === (int) $user->id);
        }

        return $this->user_id && (int) $this->user_id === (int) $user->id;
    }

    /**
     * Locate and verify an active trusted device by request cookies.
     */
    public static function findActiveByCredential(Request $request): ?self
    {
        $uuid = $request->cookie('mrpride_device_uuid') ?: $request->cookie('mrpride_device_id');
        $credential = $request->cookie('mrpride_device_credential');

        if (! $uuid || ! is_string($uuid) || ! $credential || ! is_string($credential)) {
            return null;
        }

        $device = self::where('device_uuid', $uuid)->first();
        if (! $device || ! $device->isApproved() || empty($device->credential_hash)) {
            return null;
        }

        $expectedHash = hash('sha256', $credential);
        if (! hash_equals($device->credential_hash, $expectedHash)) {
            return null;
        }

        return $device;
    }

    /**
     * Issue and securely cookie a high-entropy random credential for this device.
     */
    public function issueCredential(?string $rawCredential = null): string
    {
        $rawCredential = $rawCredential ?: Str::random(64);
        $this->credential_hash = hash('sha256', $rawCredential);
        $this->save();

        $secure = request()->isSecure();
        cookie()->queue(cookie()->forever('mrpride_device_credential', $rawCredential, httpOnly: true, sameSite: 'lax', secure: $secure));
        cookie()->queue(cookie()->forever('mrpride_device_uuid', $this->device_uuid, httpOnly: true, sameSite: 'lax', secure: $secure));
        cookie()->queue(cookie()->forever('mrpride_device_id', $this->device_uuid, httpOnly: true, sameSite: 'lax', secure: $secure));

        return $rawCredential;
    }

    /**
     * Generate a unique enrollment code formatted for easy input.
     */
    public static function generateEnrollmentCode(): string
    {
        do {
            $code = 'MRP-' . strtoupper(Str::random(6));
        } while (self::where('enrollment_code', $code)->exists());

        return $code;
    }
}

