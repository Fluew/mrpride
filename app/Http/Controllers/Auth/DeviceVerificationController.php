<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DeviceVerificationController extends Controller
{
    /**
     * Show device enrollment page.
     * Route is only accessible when opened via an active, unexpired enrollment code or invitation link.
     */
    public function showEnroll(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $code = trim((string) ($request->query('token') ?: $request->query('code')));

        // /device/enroll is closed unless accessed via an active generated invitation link or code
        if (empty($code)) {
            abort(404);
        }

        $device = TrustedDevice::where('enrollment_token', $code)
            ->orWhere('enrollment_code', strtoupper($code))
            ->with('user:id,name,email,role')
            ->first();

        if (! $device) {
            abort(404);
        }

        $expiresAt = $device->enrollment_token_expires_at ?? $device->enrollment_code_expires_at;
        if (! $expiresAt || $expiresAt->isPast()) {
            return Inertia::render('auth/AccessDenied', [
                'title' => 'Invitation Expired',
                'message' => 'This device enrollment link has expired. Workstation invitations are valid for 5 minutes only. Please request a new invitation from your administrator.',
                'canEnroll' => false,
            ])->toResponse($request)->setStatusCode(410);
        }

        // Forwarding protection: only works on the email registered by the Admin
        if ($device->user && $request->filled('email')) {
            $queryEmail = strtolower(trim((string) $request->query('email')));
            if ($queryEmail !== strtolower($device->user->email)) {
                return Inertia::render('auth/AccessDenied', [
                    'title' => 'Access Restricted',
                    'message' => "This invitation link is strictly restricted to {$device->user->email}. It cannot be accessed by other accounts even if forwarded.",
                    'canEnroll' => false,
                ])->toResponse($request)->setStatusCode(403);
            }
        }

        return Inertia::render('auth/EnrollDevice', [
            'initialCode' => '',
            'token' => $device->enrollment_token,
            'expectedCode' => $device->enrollment_code,
            'assignedUser' => $device->user ? [
                'name' => $device->user->name,
                'email' => $device->user->email,
            ] : null,
            'expiresAt' => $expiresAt->diffForHumans(),
            'isInvitation' => true,
            'status' => session('status'),
        ])->toResponse($request);
    }

    /**
     * Show enrollment page for a specific one-time invitation token.
     */
    public function showEnrollWithToken(Request $request, string $token): \Symfony\Component\HttpFoundation\Response
    {
        $device = TrustedDevice::where('enrollment_token', $token)
            ->with('user:id,name,email,role')
            ->first();

        if (! $device) {
            abort(404);
        }

        if ($device->enrollment_token_expires_at && $device->enrollment_token_expires_at->isPast()) {
            return Inertia::render('auth/AccessDenied', [
                'title' => 'Invitation Expired',
                'message' => 'This device enrollment link has expired. Workstation invitations are valid for 5 minutes only. Please request a new invitation from your administrator.',
                'canEnroll' => false,
            ])->toResponse($request)->setStatusCode(410);
        }

        // Forwarding protection: only works on the email registered by the Admin
        if ($device->user && $request->filled('email')) {
            $queryEmail = strtolower(trim((string) $request->query('email')));
            if ($queryEmail !== strtolower($device->user->email)) {
                return Inertia::render('auth/AccessDenied', [
                    'title' => 'Access Restricted',
                    'message' => "This invitation link is strictly restricted to {$device->user->email}. It cannot be accessed by other accounts even if forwarded.",
                    'canEnroll' => false,
                ])->toResponse($request)->setStatusCode(403);
            }
        }

        return Inertia::render('auth/EnrollDevice', [
            'initialCode' => '',
            'token' => $token,
            'expectedCode' => $device->enrollment_code,
            'assignedUser' => $device->user ? [
                'name' => $device->user->name,
                'email' => $device->user->email,
            ] : null,
            'expiresAt' => $device->enrollment_token_expires_at?->diffForHumans(),
            'isInvitation' => true,
            'status' => session('status'),
        ])->toResponse($request);
    }

    /**
     * Process enrollment code/token submitted by candidate browser with employee identity verification.
     */
    public function processEnroll(Request $request): RedirectResponse
    {
        $rawCode = $request->input('code') ?: $request->input('enrollment_code');
        $rawToken = $request->input('token');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'token' => ['nullable', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email'],
            'password' => ['nullable', 'string'],
        ]);

        $inputCode = strtoupper(trim($validated['code']));
        $inputToken = trim((string) ($validated['token'] ?? ''));

        // Locate device invitation
        $device = null;
        if (! empty($inputToken)) {
            $device = TrustedDevice::where('enrollment_token', $inputToken)->first();
        }
        if (! $device) {
            $device = TrustedDevice::where('enrollment_code', $inputCode)
                ->orWhere('enrollment_token', $inputCode)
                ->first();
        }

        if (! $device) {
            AuditLogger::security(
                'auth.device.enrollment_failed',
                "Device enrollment failed: Invalid code/token '{$inputCode}'.",
                null,
                'warning',
                ['ip' => $request->ip(), 'input_code' => $inputCode],
                'failure',
                $request
            );

            return back()->withErrors([
                'code' => 'The enrollment link or code is invalid. Please double-check or ask your administrator for a new invitation.',
            ]);
        }

        // If device has an enrollment_code and a token was used, verify code matches
        if ($device->enrollment_code && ! empty($inputToken)) {
            if ($inputCode !== strtoupper($device->enrollment_code)) {
                return back()->withErrors([
                    'code' => 'The enrollment code entered does not match your invitation. Please check your email.',
                ]);
            }
        }

        // Check 5-minute expiration
        $isExpired = false;
        if ($device->enrollment_code === $inputCode && $device->enrollment_code_expires_at) {
            $isExpired = $device->enrollment_code_expires_at->isPast();
        } elseif ($device->enrollment_token && ($device->enrollment_token === $inputToken || $device->enrollment_token === $inputCode)) {
            $isExpired = $device->enrollment_token_expires_at ? $device->enrollment_token_expires_at->isPast() : false;
        }

        if ($isExpired) {
            AuditLogger::security(
                'auth.device.enrollment_expired',
                "Device enrollment failed: Expired invitation for device ID {$device->id}.",
                $device->user,
                'warning',
                ['ip' => $request->ip(), 'device_id' => $device->id],
                'failure',
                $request
            );

            return back()->withErrors([
                'code' => 'This enrollment link has expired (links are strictly valid for 5 minutes only). Please ask your administrator to generate a new invitation.',
            ]);
        }

        // Employee registered email check (Forwarding protection)
        $targetUser = $device->user;
        if ($targetUser) {
            $registeredEmail = strtolower($targetUser->email);
            if (! empty($validated['email'])) {
                $submittedEmail = strtolower(trim((string) $validated['email']));
                if ($submittedEmail !== $registeredEmail) {
                    return back()->withErrors([
                        'email' => "Access Restricted: This invitation is strictly registered to {$targetUser->email}. It cannot be used with any other email even if forwarded.",
                    ]);
                }
            }
        }

        // Verify password if provided
        if ($targetUser && ! empty($validated['password'])) {
            if (! Hash::check($validated['password'], $targetUser->password)) {
                AuditLogger::security(
                    'auth.device.identity_verification_failed',
                    "Identity verification failed during enrollment for email '{$targetUser->email}'.",
                    $targetUser,
                    'warning',
                    ['ip' => $request->ip(), 'email' => $targetUser->email, 'device_id' => $device->id],
                    'failure',
                    $request
                );

                return back()->withErrors([
                    'password' => 'Incorrect temporary password or account password. Please verify credentials.',
                ]);
            }
        }

        // Activate account if pending activation
        if ($targetUser && $targetUser->status === 'pending_activation') {
            $targetUser->update(['status' => 'active']);
            AuditLogger::audit(
                'account.activated',
                "User {$targetUser->email} verified identity and activated account during workstation enrollment.",
                $targetUser,
                $targetUser,
                ['device_id' => $device->id],
                'success',
                $request
            );
        }

        // Generate high-entropy secret token & uuid
        $rawCredential = Str::random(64);
        $deviceUuid = $device->device_uuid ?: (string) Str::uuid();
        $deviceName = ! empty($validated['device_name'])
            ? trim($validated['device_name'])
            : ($device->device_name ?: $this->parseDeviceName($request));

        $device->update([
            'user_id' => $targetUser?->id ?? $device->user_id,
            'device_uuid' => $deviceUuid,
            'device_name' => $deviceName,
            'credential_hash' => hash('sha256', $rawCredential),
            'status' => 'approved',
            'approved_at' => now(),
            'enrollment_code' => null,
            'enrollment_code_expires_at' => null,
            'enrollment_token' => null,
            'enrollment_token_expires_at' => null,
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
        ]);

        // Issue HttpOnly, SameSite=Lax cookie
        $secure = $request->isSecure();
        cookie()->queue(cookie()->forever('mrpride_device_credential', $rawCredential, httpOnly: true, sameSite: 'lax', secure: $secure));
        cookie()->queue(cookie()->forever('mrpride_device_uuid', $deviceUuid, httpOnly: true, sameSite: 'lax', secure: $secure));
        cookie()->queue(cookie()->forever('mrpride_device_id', $deviceUuid, httpOnly: true, sameSite: 'lax', secure: $secure));

        AuditLogger::security(
            'auth.device.enrolled',
            "Browser enrolled as trusted workstation '{$deviceName}' ({$deviceUuid})" . ($targetUser ? " for {$targetUser->email}" : "") . ".",
            $targetUser,
            'info',
            ['device_id' => $device->id, 'device_uuid' => $deviceUuid, 'ip' => $request->ip()],
            'success',
            $request
        );

        return redirect()->route('login')->with(
            'status',
            "Workstation successfully registered and identity verified! You can now log in using your authorized account."
        );
    }

    /**
     * Show access denied view.
     */
    public function accessDenied(Request $request): Response
    {
        return Inertia::render('auth/AccessDenied', [
            'title' => 'Access Denied',
            'message' => 'This device is not registered or is no longer authorized to access MRP ElectroDesk. Contact your system administrator.',
            'canEnroll' => true,
        ]);
    }

    /**
     * Authorize a device via the one-time email link.
     */
    public function authorizeDevice(Request $request, string $token): RedirectResponse
    {
        $device = TrustedDevice::where('enrollment_token', $token)
            ->where('enrollment_token_expires_at', '>=', now())
            ->first();

        if (! $device) {
            AuditLogger::security(
                'auth.device.access_failed',
                "Device access attempt failed with invalid or expired token: {$token}",
                null,
                'warning',
                ['ip' => $request->ip()],
                'failure',
                $request
            );

            return redirect('/')->withErrors([
                'device' => 'This device authorization link is invalid or has expired. Please ask the Administrator to enroll this device.',
            ]);
        }

        $rawCredential = Str::random(64);
        $deviceUuid = $device->device_uuid ?: (string) Str::uuid();
        $deviceName = $this->parseDeviceName($request);

        $device->update([
            'device_uuid' => $deviceUuid,
            'device_name' => $deviceName,
            'credential_hash' => hash('sha256', $rawCredential),
            'ip_address' => $request->ip(),
            'status' => 'approved',
            'approved_at' => now(),
            'enrollment_code' => null,
            'enrollment_code_expires_at' => null,
            'enrollment_token' => null,
            'enrollment_token_expires_at' => null,
            'last_used_at' => now(),
        ]);

        $secure = $request->isSecure();
        cookie()->queue(cookie()->forever('mrpride_device_credential', $rawCredential, httpOnly: true, sameSite: 'lax', secure: $secure));
        cookie()->queue(cookie()->forever('mrpride_device_uuid', $deviceUuid, httpOnly: true, sameSite: 'lax', secure: $secure));
        cookie()->queue(cookie()->forever('mrpride_device_id', $deviceUuid, httpOnly: true, sameSite: 'lax', secure: $secure));

        AuditLogger::security(
            'auth.device.authorized_via_email',
            "Device '{$deviceName}' authorized via email link for {$device->user?->email}.",
            $device->user,
            'info',
            ['device_uuid' => $deviceUuid, 'ip' => $request->ip()],
            'success',
            $request
        );

        return redirect('/login')->with(
            'status',
            'Device successfully authorized! You can now log in and access your dashboard from this device.'
        );
    }

    /**
     * Parse human-friendly device name from User-Agent.
     */
    public function parseDeviceName(Request $request): string
    {
        $agent = $request->userAgent() ?? 'Unknown Device';

        $os = 'Unknown OS';
        if (str_contains($agent, 'Windows')) $os = 'Windows';
        elseif (str_contains($agent, 'Macintosh')) $os = 'macOS';
        elseif (str_contains($agent, 'Linux')) $os = 'Linux';
        elseif (str_contains($agent, 'Android')) $os = 'Android';
        elseif (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad')) $os = 'iOS';

        $browser = 'Browser';
        if (str_contains($agent, 'Edg/')) $browser = 'Edge';
        elseif (str_contains($agent, 'Chrome/')) $browser = 'Chrome';
        elseif (str_contains($agent, 'Firefox/')) $browser = 'Firefox';
        elseif (str_contains($agent, 'Safari/') && ! str_contains($agent, 'Chrome/')) $browser = 'Safari';

        return "{$os} • {$browser}";
    }
}

