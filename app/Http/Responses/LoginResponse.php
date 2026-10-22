<?php

namespace App\Http\Responses;

use App\Http\Responses\Concerns\RedirectsToCurrentTeam;
use App\Models\TrustedDevice;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    use RedirectsToCurrentTeam;

    public function toResponse($request): Response
    {
        $user = $request->user();

        // Check if account is deactivated
        if (! $user->isActive()) {
            auth()->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            AuditLogger::security(
                'auth.login.deactivated',
                "Login blocked: User {$user->email} is deactivated.",
                $user,
                'warning',
                ['role' => $user->role],
                'blocked',
                $request
            );

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been deactivated. Please contact the administrator.',
            ]);
        }

        $user->update(['last_login_at' => now()]);

        AuditLogger::security(
            'auth.login.success',
            "User {$user->email} logged in successfully.",
            $user,
            'info',
            ['role' => $user->role],
            'success',
            $request
        );

        if ($request->wantsJson()) {
            return new JsonResponse(['two_factor' => false], 200);
        }

        // Regular users go to home page
        if ($user->isUser()) {
            return redirect('/');
        }

        // Admin: device restriction removed for now (auto-approve device and continue)
        if ($user->isAdmin()) {
            $deviceUuid = $request->cookie('mrpride_device_id') ?: (string) \Illuminate\Support\Str::uuid();
            cookie()->queue(cookie()->forever('mrpride_device_id', $deviceUuid, httpOnly: true, sameSite: 'lax'));

            TrustedDevice::updateOrCreate(
                ['user_id' => $user->id, 'device_uuid' => $deviceUuid],
                [
                    'device_name' => $this->parseDeviceName($request),
                    'ip_address' => $request->ip(),
                    'status' => 'approved',
                    'last_used_at' => now(),
                ]
            );

            if ($user->isPasswordExpired()) {
                return redirect()->route('password.change');
            }

            return redirect()->intended($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
        }

        // Staff: check trusted device
        $deviceUuid = $request->cookie('mrpride_device_id');
        $device = $deviceUuid
            ? $user->trustedDevices()->where('device_uuid', $deviceUuid)->first()
            : null;

        if (! $device || ! $device->isApproved()) {
            // Generate single-use device authorization link
            $token = \Illuminate\Support\Str::random(48);
            $expiresAt = now()->addHours(24);
            $assignedUuid = $deviceUuid ?: (string) \Illuminate\Support\Str::uuid();
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
            } catch (\Throwable) {
                // Ignore email transport failure in local test environment
            }

            // Immediately log out and terminate the session on this unauthorized device
            auth()->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            AuditLogger::security(
                'auth.device.unauthorized_rerouted',
                "Unauthorized device attempted login for {$user->email}. Rerouted to home page; authorization link sent to email.",
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

        // Update device last used
        $device->updateQuietly([
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        // Check password expiration / mandatory change
        if ($user->isPasswordExpired()) {
            return redirect()->route('password.change');
        }

        if ($user->isStaff()) {
            $team = $user->currentTeam ?: $user->teams()->first() ?: \App\Models\Team::first();
            if ($team) {
                return redirect("/{$team->slug}/" . $user->assignedModuleSlug());
            }
        }

        return redirect()->intended($this->redirectPathForCurrentTeam($request, Fortify::redirects('login')));
    }

    /**
     * Parse human-friendly device name from User-Agent.
     */
    protected function parseDeviceName($request): string
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
