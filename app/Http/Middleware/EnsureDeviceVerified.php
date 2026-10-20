<?php

namespace App\Http\Middleware;

use App\Models\TrustedDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Device verification applies strictly to admin and staff personnel
        if (! $user || ! $user->isAdminOrStaff()) {
            return $next($request);
        }

        $deviceUuid = $request->cookie('mrpride_device_id');

        if (! $deviceUuid || ! is_string($deviceUuid)) {
            $deviceUuid = (string) Str::uuid();
            cookie()->queue(cookie()->forever('mrpride_device_id', $deviceUuid, httpOnly: true, sameSite: 'lax'));
        }

        $device = TrustedDevice::where('user_id', $user->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        // If this admin user has no devices at all (e.g. system setup), auto-register first device
        if (! $device && $user->isAdmin() && $user->trustedDevices()->count() === 0) {
            $device = TrustedDevice::create([
                'user_id' => $user->id,
                'device_uuid' => $deviceUuid,
                'device_name' => $this->parseDeviceName($request),
                'ip_address' => $request->ip(),
                'status' => 'approved',
                'last_used_at' => now(),
            ]);
        }

        if ($device && $device->isApproved()) {
            // Update last used timestamp and IP
            $device->updateQuietly([
                'last_used_at' => now(),
                'ip_address' => $request->ip(),
            ]);

            return $next($request);
        }

        if ($request->route()?->getName() === 'logout') {
            return $next($request);
        }

        // Generate single-use authorization token
        $token = Str::random(48);
        $expiresAt = now()->addHours(24);
        $assignedUuid = $deviceUuid ?: (string) Str::uuid();
        cookie()->queue(cookie()->forever('mrpride_device_id', $assignedUuid, httpOnly: true, sameSite: 'lax'));

        $deviceName = $this->parseDeviceName($request);

        TrustedDevice::updateOrCreate(
            ['user_id' => $user->id, 'device_uuid' => $assignedUuid],
            [
                'device_name' => $deviceName,
                'ip_address' => $request->ip(),
                'status' => 'pending',
                'enrollment_token' => $token,
                'enrollment_token_expires_at' => $expiresAt,
            ]
        );

        $accessUrl = url("/device/access/{$token}");

        try {
            $user->notify(new \App\Notifications\Auth\DeviceAccessNotification(
                accessUrl: $accessUrl,
                deviceName: $deviceName,
                ipAddress: $request->ip() ?? 'Unknown',
                expiresAt: $expiresAt->format('M d, Y H:i')
            ));
        } catch (\Throwable) {}

        auth()->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        \App\Services\AuditLogger::security(
            'auth.device.unauthorized_rerouted',
            "Unauthorized device attempted workspace access for {$user->email}. Rerouted to home page; authorization link sent to email.",
            $user,
            'warning',
            ['ip' => $request->ip(), 'device' => $deviceName],
            'blocked',
            $request
        );

        $parts = explode('@', $user->email);
        $maskedEmail = substr($parts[0], 0, 1) . '***@' . ($parts[1] ?? '');

        return redirect('/')->with(
            'device_notice',
            "Unauthorized device detected. A secure authorization link has been sent to your email ({$maskedEmail}). Open the link on this device to access the login page and dashboard."
        );
    }

    /**
     * Parse human-friendly device name from User-Agent.
     */
    protected function parseDeviceName(Request $request): string
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
