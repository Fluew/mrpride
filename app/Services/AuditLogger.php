<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Record an audit or security log event.
     *
     * @param  string  $event
     * @param  string  $description
     * @param  User|null  $user
     * @param  array<string, mixed>|null  $metadata
     * @param  Request|null  $request
     * @param  string  $category 'audit' or 'security'
     * @param  string  $severity 'info', 'warning', 'danger'
     * @param  User|null  $targetUser
     * @param  string  $outcome 'success', 'failure', 'blocked'
     * @return AuditLog
     */
    public static function log(
        string $event,
        string $description,
        ?User $user = null,
        ?array $metadata = null,
        ?Request $request = null,
        string $category = 'audit',
        string $severity = 'info',
        ?User $targetUser = null,
        string $outcome = 'success'
    ): AuditLog {
        $req = $request ?: request();

        // Sanitize metadata to never log passwords, tokens, secrets or session IDs
        $sanitizedMetadata = self::sanitizeMetadata($metadata ?? []);

        $log = AuditLog::create([
            'category' => $category,
            'user_id' => $user?->id,
            'user_email' => $user?->email ?? ($req ? $req->input('email') : null),
            'target_user_id' => $targetUser?->id,
            'target_user_email' => $targetUser?->email,
            'event' => $event,
            'action_type' => $event,
            'description' => $description,
            'outcome' => $outcome,
            'severity' => $severity,
            'ip_address' => $req?->ip(),
            'user_agent' => $req?->userAgent(),
            'metadata' => $sanitizedMetadata ?: null,
            'created_at' => now(),
        ]);

        Log::info("[{$category}:{$severity}] {$event}: {$description}", [
            'user_id' => $user?->id,
            'target_user_id' => $targetUser?->id,
            'ip' => $req?->ip(),
            'outcome' => $outcome,
        ]);

        return $log;
    }

    /**
     * Record an administrative audit action (Audit Trail).
     */
    public static function audit(
        string $actionType,
        string $description,
        ?User $actingUser = null,
        ?User $targetUser = null,
        ?array $metadata = null,
        string $outcome = 'success',
        ?Request $request = null
    ): AuditLog {
        return self::log(
            event: $actionType,
            description: $description,
            user: $actingUser,
            metadata: $metadata,
            request: $request,
            category: 'audit',
            severity: 'info',
            targetUser: $targetUser,
            outcome: $outcome
        );
    }

    /**
     * Record a security, authentication, or access control event (Security Logs).
     */
    public static function security(
        string $eventType,
        string $description,
        ?User $user = null,
        string $severity = 'info',
        ?array $metadata = null,
        string $outcome = 'success',
        ?Request $request = null
    ): AuditLog {
        return self::log(
            event: $eventType,
            description: $description,
            user: $user,
            metadata: $metadata,
            request: $request,
            category: 'security',
            severity: $severity,
            outcome: $outcome
        );
    }

    /**
     * Remove sensitive keys from metadata array.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function sanitizeMetadata(array $data): array
    {
        $sensitive = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'session_id',
            'remember_token',
            'verification_code',
            'recovery_code',
            'key',
            'credential',
        ];

        $cleaned = [];
        foreach ($data as $k => $v) {
            $lower = strtolower($k);
            $isSensitive = false;
            foreach ($sensitive as $s) {
                if (str_contains($lower, $s)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $cleaned[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $cleaned[$k] = self::sanitizeMetadata($v);
            } else {
                $cleaned[$k] = $v;
            }
        }

        return $cleaned;
    }
}
