<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Artisan::command('device:enroll-code {--user= : User ID or email} {--name= : Device name} {--shared : Allow shared workstation} {--minutes=60 : Expiration in minutes}', function () {
    $userId = $this->option('user');
    $user = null;
    if ($userId) {
        $user = is_numeric($userId)
            ? \App\Models\User::find($userId)
            : \App\Models\User::where('email', $userId)->first();

        if (! $user) {
            $this->error("User not found: {$userId}");
            return 1;
        }
    }

    $isShared = (bool) $this->option('shared');
    $deviceName = $this->option('name') ?: ($user ? "{$user->name}'s Workstation" : 'Workstation');
    $minutes = (int) $this->option('minutes') ?: 60;
    $code = \App\Models\TrustedDevice::generateEnrollmentCode();

    $device = \App\Models\TrustedDevice::create([
        'device_uuid' => (string) \Illuminate\Support\Str::uuid(),
        'device_name' => $deviceName,
        'user_id' => $user?->id,
        'status' => 'pending',
        'is_shared' => $isShared,
        'authorized_user_ids' => $user && $isShared ? [$user->id] : null,
        'enrollment_code' => $code,
        'enrollment_code_expires_at' => now()->addMinutes($minutes),
    ]);

    $this->info("==================================================");
    $this->info(" Enrollment Code: {$code}");
    $this->info(" Device Name:     {$deviceName}");
    $this->info(" Assigned User:   " . ($user ? "{$user->name} ({$user->email})" : 'None (Shared/Unassigned)'));
    $this->info(" Mode:            " . ($isShared ? 'Shared Workstation' : 'Single User Binding'));
    $this->info(" Expires:         " . now()->addMinutes($minutes)->toDateTimeString() . " ({$minutes} mins)");
    $this->info("==================================================");
    $this->comment("Open in the browser on your laptop/phone:");
    $this->comment("http://<your-laptop-ip>:8000/device/enroll");
    $this->info("==================================================");

    return 0;
})->purpose('Generate a single-use trusted device enrollment code');

Artisan::command('device:list', function () {
    $devices = \App\Models\TrustedDevice::with('user', 'approver')->get();
    if ($devices->isEmpty()) {
        $this->warn('No devices registered.');
        return 0;
    }

    $rows = $devices->map(fn ($d) => [
        'ID' => $d->id,
        'Name' => $d->device_name,
        'Status' => $d->status,
        'Assigned User' => $d->user?->name ?: ($d->is_shared ? 'Shared (' . count($d->authorized_user_ids ?? []) . ' users)' : 'None'),
        'Shared' => $d->is_shared ? 'Yes' : 'No',
        'Last Verified' => $d->last_verified_at?->diffForHumans() ?: 'Never',
        'Active Credential' => ! empty($d->credential_hash) ? 'Yes' : 'No',
    ]);

    $this->table(['ID', 'Name', 'Status', 'Assigned User', 'Shared', 'Last Verified', 'Active Credential'], $rows);
    return 0;
})->purpose('List registered and pending devices');

