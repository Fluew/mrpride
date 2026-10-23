<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DeviceVerificationController extends Controller
{
    /**
     * Show device enrollment page.
     */
    public function showEnroll(Request $request): Response
    {
        return Inertia::render('auth/EnrollDevice', [
            'initialCode' => $request->query('code', ''),
            'status' => session('status'),
        ]);
    }

    /**
     * Process enrollment code submitted by candidate browser.
     */
    public function processEnroll(Request $request): RedirectResponse
    {
        $rawCode = $request->input('code') ?: $request->input('enrollment_code');
        if ($rawCode) {
            $request->merge(['code' => $rawCode]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $inputCode = strtoupper(trim($validated['code']));

        // Find device by enrollment_code or enrollment_token
        $device = TrustedDevice::where(function ($q) use ($inputCode) {
            $q->where('enrollment_code', $inputCode)
                ->orWhere('enrollment_token', $inputCode);
        })->first();

        if (! $device) {
            AuditLogger::security(
                'auth.device.enrollment_failed',
                "Device enrollment failed: Invalid code '{$inputCode}'.",
                null,
                'warning',
                ['ip' => $request->ip(), 'input_code' => $inputCode],
                'failure',
                $request
            );

            return back()->withErrors([
                'code' => 'The enrollment code is invalid. Please double-check the code or contact your administrator.',
                'enrollment_code' => 'The enrollment code is invalid. Please double-check the code or contact your administrator.',
            ]);
        }

        // Check expiration
        $isExpired = false;
        if ($device->enrollment_code === $inputCode && $device->enrollment_code_expires_at) {
            $isExpired = $device->enrollment_code_expires_at->isPast();
        } elseif ($device->enrollment_token === $inputCode && $device->enrollment_token_expires_at) {
            $isExpired = $device->enrollment_token_expires_at->isPast();
        }

        if ($isExpired) {
            AuditLogger::security(
                'auth.device.enrollment_expired',
                "Device enrollment failed: Expired code '{$inputCode}' for device ID {$device->id}.",
                $device->user,
                'warning',
                ['ip' => $request->ip(), 'device_id' => $device->id],
                'failure',
                $request
            );

            return back()->withErrors([
                'code' => 'This enrollment code has expired. Please ask your administrator to generate a new code.',
                'enrollment_code' => 'This enrollment code has expired. Please ask your administrator to generate a new code.',
            ]);
        }

        // Generate high-entropy secret token & uuid
        $rawCredential = Str::random(64);
        $deviceUuid = $device->device_uuid ?: (string) Str::uuid();
        $deviceName = ! empty($validated['device_name'])
            ? trim($validated['device_name'])
            : ($device->device_name ?: $this->parseDeviceName($request));

        $device->update([
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
            "Browser enrolled as trusted workstation '{$deviceName}' ({$deviceUuid}).",
            $device->user,
            'info',
            ['device_id' => $device->id, 'device_uuid' => $deviceUuid, 'ip' => $request->ip()],
            'success',
            $request
        );

        return redirect()->route('login')->with(
            'status',
            "Workstation '{$deviceName}' successfully registered! You can now log in using your authorized account."
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

