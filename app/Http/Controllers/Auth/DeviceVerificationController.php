<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TrustedDevice;
use App\Notifications\Auth\DeviceAccessNotification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceVerificationController extends Controller
{
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
                'device' => 'This device authorization link is invalid or has expired. Please log in again to generate a new link, or ask the Administrator to authorize this device.',
            ]);
        }

        // 1 Account, 1 Device Policy: Remove any previously registered devices for this user
        TrustedDevice::where('user_id', $device->user_id)
            ->where('id', '!=', $device->id)
            ->delete();

        // Terminate any existing sessions across other devices
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $device->user_id)->delete();

        // Set or refresh the permanent device cookie
        $deviceUuid = $device->device_uuid ?: (string) Str::uuid();
        cookie()->queue(cookie()->forever('mrpride_device_id', $deviceUuid, httpOnly: true, sameSite: 'lax'));

        $deviceName = $this->parseDeviceName($request);

        $device->update([
            'device_uuid' => $deviceUuid,
            'device_name' => $deviceName,
            'ip_address' => $request->ip(),
            'status' => 'approved',
            'enrollment_token' => null,
            'enrollment_token_expires_at' => null,
            'last_used_at' => now(),
        ]);

        AuditLogger::security(
            'auth.device.authorized_via_email',
            "Device '{$deviceName}' authorized via email link for {$device->user->email}. 1-Device policy enforced: prior devices and sessions cleared.",
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
