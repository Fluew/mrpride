<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ResendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display centralized User Management screen.
     * Accessible to Owner/Superadmin and Admin.
     * Tabs:
     * - accounts (Superadmin & Admin)
     * - sessions (Superadmin only)
     * - devices (Superadmin only)
     * - audit (Superadmin only)
     * - security (Superadmin only)
     */
    public function index(Request $request): Response
    {
        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'User Management requires administrative privileges.');

        $team = $currentUser->currentTeam;
        abort_if(! $team, 403, 'No active team selected.');

        $activeTab = $request->input('tab', 'accounts');

        $search = $request->input('search');
        $roleFilter = $request->input('role', 'All Roles');
        $statusFilter = $request->input('status', 'All Statuses');

        // 1. User Accounts Data
        $accountsData = [];
        if ($activeTab === 'accounts') {
            $query = $team->members()->withPivot('role')->withCount('trustedDevices');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($roleFilter && $roleFilter !== 'All Roles') {
                $query->where(function ($q) use ($roleFilter) {
                    $q->wherePivot('role', strtolower($roleFilter))
                        ->orWhere('role', strtolower($roleFilter));
                });
            }

            if ($statusFilter && $statusFilter !== 'All Statuses') {
                $query->where('status', strtolower($statusFilter));
            }

            $accountsData = $query->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roleLabel(),
                'raw_role' => $user->role,
                'status' => $user->status === 'pending_activation' ? 'Pending Activation' : ucfirst($user->status ?? 'active'),
                'raw_status' => $user->status ?? 'active',
                'is_active' => ($user->status ?? 'active') === 'active',
                'is_pending_activation' => $user->status === 'pending_activation',
                'must_change_password' => (bool) $user->must_change_password,
                'password_changed_at' => $user->password_changed_at?->format('M d, Y'),
                'last_login_at' => $user->last_login_at?->format('M d, Y H:i') ?? 'Never',
                'joined_at' => $user->pivot->created_at?->format('M d, Y') ?? $user->created_at?->format('M d, Y'),
                'trusted_devices_count' => $user->trusted_devices_count ?? 0,
                'is_superadmin' => false,
            ]);
        }

        // 2. Active Sessions Data (Admin accessible)
        $sessionsData = [];
        if ($activeTab === 'sessions') {
            $currentSessionId = $request->session()->getId();

            $sessionsQuery = DB::table('sessions')
                ->join('users', 'sessions.user_id', '=', 'users.id')
                ->select(
                    'sessions.id',
                    'sessions.user_id',
                    'sessions.ip_address',
                    'sessions.user_agent',
                    'sessions.last_activity',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.role as user_role'
                );

            if ($search) {
                $sessionsQuery->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%")
                        ->orWhere('sessions.ip_address', 'like', "%{$search}%");
                });
            }

            $sessionsData = $sessionsQuery->orderByDesc('sessions.last_activity')->get()->map(function ($s) use ($currentSessionId) {
                $lastActivity = Carbon::createFromTimestamp($s->last_activity);
                $isExpired = $lastActivity->diffInMinutes(now()) >= config('session.lifetime', 120);
                $deviceDetails = $this->parseAgent($s->user_agent);

                return [
                    'id' => $s->id,
                    'masked_id' => 'sess_' . substr(hash('sha256', $s->id), 0, 12),
                    'user_id' => $s->user_id,
                    'user_name' => $s->user_name,
                    'user_email' => $s->user_email,
                    'user_role' => ucfirst($s->user_role),
                    'ip_address' => $s->ip_address,
                    'user_agent' => $s->user_agent,
                    'browser' => $deviceDetails['browser'],
                    'os' => $deviceDetails['os'],
                    'is_current' => $s->id === $currentSessionId,
                    'last_activity' => $lastActivity->diffForHumans(),
                    'last_activity_exact' => $lastActivity->format('M d, Y H:i:s'),
                    'is_expired' => $isExpired,
                    'status' => $isExpired ? 'Expired' : 'Active',
                ];
            });
        }

        // 3. Trusted Devices Data (Admin accessible - 1 Account = 1 Device)
        $devicesData = [];
        if ($activeTab === 'devices') {
            $devicesQuery = TrustedDevice::with('user:id,name,email,role');

            if ($search) {
                $devicesQuery->where(function ($q) use ($search) {
                    $q->where('device_name', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            $currentCookieUuid = $request->cookie('mrpride_device_uuid') ?: $request->cookie('mrpride_device_id');
            $allStaffUsers = User::where(function ($q) {
                $q->whereIn('role', ['admin', 'superadmin', 'owner'])
                  ->orWhere('role', 'like', 'staff%');
            })->get(['id', 'name', 'email', 'role'])->keyBy('id');

            $devicesData = $devicesQuery->orderByDesc('last_used_at')->get()->map(function (TrustedDevice $d) use ($currentCookieUuid, $allStaffUsers) {
                $authorizedUsers = [];
                if ($d->is_shared && is_array($d->authorized_user_ids)) {
                    foreach ($d->authorized_user_ids as $uid) {
                        if ($u = $allStaffUsers->get($uid)) {
                            $authorizedUsers[] = [
                                'id' => $u->id,
                                'name' => $u->name,
                                'email' => $u->email,
                                'role' => $u->roleLabel(),
                            ];
                        }
                    }
                }

                $enrollmentCode = $d->enrollment_code ?: $d->enrollment_token;
                $isEnrollmentExpired = false;
                if ($d->enrollment_code) {
                    $isEnrollmentExpired = $d->enrollment_code_expires_at ? $d->enrollment_code_expires_at->isPast() : true;
                } elseif ($d->enrollment_token) {
                    $isEnrollmentExpired = $d->enrollment_token_expires_at ? $d->enrollment_token_expires_at->isPast() : true;
                }

                return [
                    'id' => $d->id,
                    'device_uuid' => $d->device_uuid,
                    'device_name' => $d->device_name,
                    'ip_address' => $d->ip_address,
                    'status' => ucfirst($d->status),
                    'raw_status' => $d->status,
                    'is_shared' => (bool) $d->is_shared,
                    'is_current' => $d->device_uuid === $currentCookieUuid,
                    'last_used_at' => $d->last_used_at?->diffForHumans() ?? 'Never',
                    'created_at' => $d->created_at?->format('M d, Y'),
                    'enrollment_code' => $enrollmentCode,
                    'enrollment_link' => $d->enrollment_token ? url("/device/enroll/{$d->enrollment_token}") : ($enrollmentCode ? url("/device/enroll?code={$enrollmentCode}") : null),
                    'is_enrollment_expired' => $isEnrollmentExpired,
                    'user' => $d->user ? [
                        'id' => $d->user->id,
                        'name' => $d->user->name,
                        'email' => $d->user->email,
                        'role' => $d->user->roleLabel(),
                    ] : null,
                    'authorized_users' => $authorizedUsers,
                ];
            });
        }

        // 4. Audit Trail Data (Admin accessible)
        $auditData = null;
        if ($activeTab === 'audit') {
            $auditQuery = AuditLog::where('category', 'audit')
                ->with(['user:id,name,email', 'targetUser:id,name,email']);

            if ($search) {
                $auditQuery->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('user_email', 'like', "%{$search}%")
                        ->orWhere('target_user_email', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%");
                });
            }

            if ($request->input('event_filter')) {
                $auditQuery->where('event', $request->input('event_filter'));
            }

            if ($request->input('date_from')) {
                $auditQuery->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->input('date_to')) {
                $auditQuery->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $auditData = $auditQuery->orderByDesc('created_at')->paginate(20)->withQueryString();
        }

        // 5. Security Logs Data (Admin accessible)
        $securityData = null;
        if ($activeTab === 'security') {
            $secQuery = AuditLog::where('category', 'security')
                ->with(['user:id,name,email']);

            if ($search) {
                $secQuery->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('user_email', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%");
                });
            }

            if ($request->input('severity')) {
                $secQuery->where('severity', $request->input('severity'));
            }

            if ($request->input('date_from')) {
                $secQuery->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->input('date_to')) {
                $secQuery->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $securityData = $secQuery->orderByDesc('created_at')->paginate(20)->withQueryString();
        }

        $usersList = ! empty($accountsData)
            ? $accountsData
            : $team->members()->get()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->roleLabel(),
                'raw_role' => $u->role,
                'status' => $u->status === 'pending_activation' ? 'Pending Activation' : ucfirst($u->status ?? 'active'),
                'raw_status' => $u->status ?? 'active',
                'is_active' => ($u->status ?? 'active') === 'active',
                'is_pending_activation' => $u->status === 'pending_activation',
                'trusted_devices_count' => $u->trustedDevices()->count(),
                'is_superadmin' => false,
            ]);

        return Inertia::render('Users/Index', [
            'activeTab' => $activeTab,
            'isSuperAdmin' => true,
            'users' => $usersList,
            'sessions' => $sessionsData,
            'devices' => $devicesData,
            'auditLogs' => $auditData,
            'securityLogs' => $securityData,
            'adminRecoveryKey' => $currentUser->admin_recovery_key,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
                'status' => $statusFilter,
                'event_filter' => $request->input('event_filter'),
                'severity' => $request->input('severity'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
            'roles' => [
                ['value' => 'admin', 'label' => 'Administrator (All Modules)'],
                ['value' => 'staff_inventory', 'label' => 'Staff - Inventory'],
                ['value' => 'staff_quotations', 'label' => 'Staff - Quotations'],
                ['value' => 'staff_procurement', 'label' => 'Staff - Procurement'],
                ['value' => 'staff_deliveries', 'label' => 'Staff - Deliveries'],
                ['value' => 'staff_sales', 'label' => 'Staff - Sales'],
                ['value' => 'staff_forecast', 'label' => 'Staff - Demand Forecasting'],
                ['value' => 'staff_suppliers', 'label' => 'Staff - Suppliers'],
                ['value' => 'staff_reports', 'label' => 'Staff - Reports & Analytics'],
            ],
            'temporaryPasswordNotice' => session('temporaryPasswordNotice'),
            'enrollmentNotice' => session('enrollmentNotice'),
        ]);
    }

    /**
     * Add a new user account.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'User Management requires administrative privileges.');

        $team = $currentUser->currentTeam;
        abort_if(! $team, 403, 'No active team selected.');

        $allowedRoles = [
            'admin',
            'staff_inventory',
            'staff_quotations',
            'staff_procurement',
            'staff_deliveries',
            'staff_sales',
            'staff_forecast',
            'staff_suppliers',
            'staff_reports',
            'staff',
            'user',
        ];

        if ($request->filled('first_name') || $request->filled('last_name')) {
            $validated = $request->validate([
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'role' => ['required', Rule::in($allowedRoles)],
            ]);
            $fullName = trim($validated['first_name'] . ' ' . $validated['last_name']);
            $firstName = $validated['first_name'];
        } else {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'role' => ['required', Rule::in($allowedRoles)],
            ]);
            $fullName = trim($validated['name']);
            $nameParts = preg_split('/\s+/', $fullName);
            $firstName = $nameParts[0] ?? 'User';
        }

        // Initial password on first login MUST strictly be (Firstname)1234
        $cleanFirst = preg_replace('/[^A-Za-z0-9]/', '', $firstName);
        $cleanFirst = ! empty($cleanFirst) ? ucfirst(strtolower($cleanFirst)) : 'User';
        $rawPassword = $cleanFirst . '1234';

        $user = User::create([
            'name' => $fullName,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => 'pending_activation',
            'password' => Hash::make($rawPassword),
            'must_change_password' => true,
            'password_changed_at' => now(),
            'email_verified_at' => now(),
        ]);

        $teamRole = $validated['role'] === 'admin' ? 'admin' : (str_starts_with($validated['role'], 'staff') ? 'staff' : 'member');
        $team->members()->attach($user->id, ['role' => $teamRole]);

        AuditLogger::audit(
            'account.created',
            "Administrator {$currentUser->email} created user {$user->email} with role '{$user->roleLabel()}'. Status set to Pending Activation. Initial temporary password set to (Firstname)1234.",
            $currentUser,
            $user,
            ['role' => $user->role, 'status' => 'pending_activation', 'must_change_password' => true],
            'success',
            $request
        );

        $temporaryPasswordNotice = "Staff account created for {$fullName}. Status: Pending Activation. Initial temporary password: {$rawPassword}. Issue a 10-minute device enrollment invitation to activate their workstation.";

        return back()
            ->with('success', "Staff account created (Pending Activation). Initial temporary password: {$rawPassword}")
            ->with('temporaryPasswordNotice', $temporaryPasswordNotice);
    }

    /**
     * Update user details and role.
     */
    public function update(Request $request, mixed $team, ?User $user = null): RedirectResponse
    {
        if ($team instanceof User) {
            $user = $team;
        }
        abort_if(! $user, 404, 'User not found.');

        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'User Management requires administrative privileges.');

        // Prevent self-role modification
        if ($currentUser->id === $user->id) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        $allowedRoles = [
            'admin',
            'staff_inventory',
            'staff_quotations',
            'staff_procurement',
            'staff_deliveries',
            'staff_sales',
            'staff_forecast',
            'staff_suppliers',
            'staff_reports',
            'staff',
            'user',
        ];

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in($allowedRoles)],
        ]);

        $name = $user->name;
        if (! empty($validated['first_name']) && ! empty($validated['last_name'])) {
            $name = trim($validated['first_name'] . ' ' . $validated['last_name']);
        } elseif (! empty($validated['name'])) {
            $name = trim($validated['name']);
        }

        $oldRole = $user->role;
        $newRole = $validated['role'];

        $user->update([
            'name' => $name,
            'role' => $newRole,
        ]);

        $teamRole = $newRole === 'admin' ? 'admin' : (str_starts_with($newRole, 'staff') ? 'staff' : 'member');
        $currentUser->currentTeam?->members()->updateExistingPivot($user->id, ['role' => $teamRole]);

        // If role changed, invalidate user's active sessions immediately
        if ($oldRole !== $newRole) {
            DB::table('sessions')->where('user_id', $user->id)->delete();

            AuditLogger::audit(
                'account.role_changed',
                "Administrator {$currentUser->email} changed role of {$user->email} from '{$oldRole}' to '{$newRole}'. Active sessions revoked.",
                $currentUser,
                $user,
                ['old_role' => $oldRole, 'new_role' => $newRole],
                'success',
                $request
            );
        } else {
            AuditLogger::audit(
                'account.updated',
                "Administrator {$currentUser->email} updated profile details for {$user->email}.",
                $currentUser,
                $user,
                null,
                'success',
                $request
            );
        }

        return back()->with('success', 'User account updated successfully.');
    }

    /**
     * Toggle account activation status (Active / Inactive).
     */
    public function toggleStatus(Request $request, mixed $team, ?User $user = null): RedirectResponse
    {
        if ($team instanceof User) {
            $user = $team;
        }
        abort_if(! $user, 404, 'User not found.');
        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'Account activation control is restricted to Administrator.');

        if ($currentUser->id === $user->id) {
            return back()->withErrors(['status' => 'You cannot deactivate your own administrator account.']);
        }

        $newStatus = $user->isActive() ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        if ($newStatus === 'inactive') {
            // Revoke all sessions immediately
            DB::table('sessions')->where('user_id', $user->id)->delete();

            AuditLogger::audit(
                'account.deactivated',
                "Administrator {$currentUser->email} deactivated account {$user->email}. Active sessions revoked.",
                $currentUser,
                $user,
                ['status' => 'inactive'],
                'success',
                $request
            );
        } else {
            AuditLogger::audit(
                'account.activated',
                "Administrator {$currentUser->email} activated account {$user->email}.",
                $currentUser,
                $user,
                ['status' => 'active'],
                'success',
                $request
            );
        }

        return back()->with('success', "Account {$user->email} has been set to " . ucfirst($newStatus) . ".");
    }

    /**
     * Remove user account and revoke their sessions.
     */
    public function destroy(Request $request, mixed $team, ?User $user = null): RedirectResponse
    {
        if ($team instanceof User) {
            $user = $team;
        }

        abort_if(! $user, 404, 'User not found.');

        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'User Management requires administrative privileges.');

        if ($user->id === $currentUser->id) {
            return back()->withErrors(['user' => 'You cannot remove your own administrator account.']);
        }

        // Invalidate all active sessions
        DB::table('sessions')->where('user_id', $user->id)->delete();

        // Revoke trusted devices
        $user->trustedDevices()->delete();

        $currentUser->currentTeam?->members()->detach($user->id);
        $user->delete();

        AuditLogger::audit(
            'account.deleted',
            "Administrator {$currentUser->email} deleted user {$user->email}. Sessions and devices terminated.",
            $currentUser,
            null,
            ['deleted_user_email' => $user->email],
            'success',
            $request
        );

        return back()->with('success', 'User removed and sessions revoked successfully.');
    }

    /**
     * Revoke an individual active session (Admin accessible).
     */
    public function revokeSession(Request $request, mixed $team, ?string $sessionId = null): RedirectResponse
    {
        $actualSessionId = $sessionId ?? (is_string($team) ? $team : null);
        abort_if(! $actualSessionId, 404, 'Session ID is required.');

        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'Revoking sessions is restricted to Administrator.');

        $sessionRecord = DB::table('sessions')->where('id', $actualSessionId)->first();
        if ($sessionRecord) {
            DB::table('sessions')->where('id', $actualSessionId)->delete();

            AuditLogger::audit(
                'session.revoked',
                "Administrator {$currentUser->email} revoked active session for user ID {$sessionRecord->user_id}.",
                $currentUser,
                null,
                ['session_id_masked' => substr(hash('sha256', $actualSessionId), 0, 10)],
                'success',
                $request
            );
        }

        return back()->with('success', 'Session revoked successfully.');
    }

    /**
     * Revoke all active sessions belonging to a specific user (Admin accessible).
     */
    public function revokeUserSessions(Request $request, mixed $team, ?User $user = null): RedirectResponse
    {
        if ($team instanceof User) {
            $user = $team;
        }
        abort_if(! $user, 404, 'User not found.');

        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'Revoking user sessions is restricted to Administrator.');

        $count = DB::table('sessions')->where('user_id', $user->id)->count();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        AuditLogger::audit(
            'session.revoked_all_for_user',
            "Administrator {$currentUser->email} terminated all {$count} active sessions for {$user->email}.",
            $currentUser,
            $user,
            ['revoked_count' => $count],
            'success',
            $request
        );

        return back()->with('success', "All {$count} active session(s) for {$user->name} have been revoked.");
    }

    /**
     * Initiate trusted device enrollment or directly add an approved device.
     * Supports both single assigned accounts and multi-account shared workstations.
     */
    public function enrollDevice(Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'Device enrollment is restricted to Administrator.');

        $validated = $request->validate([
            'assignment_type' => ['required', 'string', 'in:single,shared'],
            'user_id' => ['nullable', 'required_if:assignment_type,single', 'exists:users,id'],
            'authorized_user_ids' => ['nullable', 'required_if:assignment_type,shared', 'array'],
            'authorized_user_ids.*' => ['exists:users,id'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'direct_approve' => ['nullable', 'boolean'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        $assignmentType = $validated['assignment_type'];
        $directApprove = (bool) ($validated['direct_approve'] ?? false);

        $minutes = 5;
        if (! empty($validated['expires_in_minutes'])) {
            $minutes = max(1, min(60, (int) $validated['expires_in_minutes']));
        } elseif (! empty($validated['expires_in_hours'])) {
            $minutes = (int) $validated['expires_in_hours'] * 60;
        }

        $enrollmentCode = TrustedDevice::generateEnrollmentCode();
        $token = Str::random(48);
        $expiresAt = now()->addMinutes($minutes);
        $deviceUuid = (string) Str::uuid();

        $targetUser = null;
        if ($assignmentType === 'single') {
            $targetUser = User::findOrFail($validated['user_id']);
            abort_if(! $targetUser->isAdminOrStaff(), 422, 'Trusted devices are only applicable to Admin and Staff accounts.');
            // Enforce single active device per user policy for single-user devices
            TrustedDevice::where('user_id', $targetUser->id)->where('is_shared', false)->delete();
        }

        $device = TrustedDevice::create([
            'user_id' => $assignmentType === 'single' ? $targetUser?->id : null,
            'is_shared' => $assignmentType === 'shared',
            'authorized_user_ids' => $assignmentType === 'shared' ? array_map('intval', $validated['authorized_user_ids'] ?? []) : null,
            'device_uuid' => $deviceUuid,
            'device_name' => $validated['device_name'] ?: ($assignmentType === 'shared' ? 'Shared Workstation' : "{$targetUser?->name}'s Device"),
            'status' => $directApprove ? 'approved' : 'pending',
            'approved_by' => $directApprove ? $currentUser->id : null,
            'approved_at' => $directApprove ? now() : null,
            'enrollment_code' => $directApprove ? null : $enrollmentCode,
            'enrollment_code_expires_at' => $directApprove ? null : $expiresAt,
            'enrollment_token' => $directApprove ? null : $token,
            'enrollment_token_expires_at' => $directApprove ? null : $expiresAt,
            'last_used_at' => $directApprove ? now() : null,
        ]);

        $targetEmail = $targetUser?->email;
        $emailParam = $targetEmail ? ('?email=' . urlencode($targetEmail)) : '';
        $enrollmentLink = $directApprove ? null : url("/device/enroll/{$token}{$emailParam}");

        // Dispatch invitation email to the employee's registered email
        if (! $directApprove && $targetUser && $targetUser->email) {
            try {
                app(ResendService::class)->sendDeviceEnrollmentInvitation(
                    $targetUser->email,
                    $targetUser->name,
                    $enrollmentCode,
                    $enrollmentLink,
                    $minutes
                );
            } catch (\Throwable $e) {
                Log::warning("Could not dispatch device enrollment invitation email: " . $e->getMessage());
            }
        }

        AuditLogger::audit(
            $directApprove ? 'device.directly_approved' : 'device.enrollment_initiated',
            "Administrator {$currentUser->email} created device enrollment invitation for '{$device->device_name}' (Policy: " . ($device->is_shared ? 'Shared Workstation' : 'Single Account') . ", Expires: {$minutes}m).",
            $currentUser,
            $targetUser,
            ['device_id' => $device->id, 'device_uuid' => $deviceUuid, 'code' => $enrollmentCode, 'expires_in_minutes' => $minutes],
            'success',
            $request
        );

        if ($directApprove) {
            return back()->with('success', "Device '{$device->device_name}' added and approved directly.");
        }

        return back()->with('enrollmentNotice', [
            'device_name' => $device->device_name,
            'user_name' => $targetUser?->name ?? 'Shared Workstation',
            'user_email' => $targetUser?->email ?? 'Multiple Authorized Staff',
            'code' => $enrollmentCode,
            'link' => $enrollmentLink,
            'expires_at' => $expiresAt->format('h:i A') . ' (5 minutes only)',
            'expires_in_minutes' => $minutes,
        ])->with('success', "Device enrollment code created: {$enrollmentCode}. Link expires in {$minutes} minutes.");
    }

    /**
     * Rename a trusted device (Admin accessible).
     */
    public function renameDevice(Request $request, mixed $team, ?TrustedDevice $device = null): RedirectResponse
    {
        if ($team instanceof TrustedDevice) {
            $device = $team;
        }
        abort_if(! $device, 404, 'Device not found.');

        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'Renaming devices is restricted to Administrator.');

        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $oldName = $device->device_name;
        $device->update(['device_name' => trim($validated['device_name'])]);

        AuditLogger::audit(
            'device.renamed',
            "Administrator {$currentUser->email} renamed device #{$device->id} from '{$oldName}' to '{$device->device_name}'.",
            $currentUser,
            $device->user,
            ['device_id' => $device->id, 'old_name' => $oldName, 'new_name' => $device->device_name],
            'success',
            $request
        );

        return back()->with('success', "Device successfully renamed to '{$device->device_name}'.");
    }

    /**
     * Revoke a trusted device (Admin accessible).
     * Immediately invalidates device access and active user sessions.
     */
    public function revokeDevice(Request $request, mixed $team, ?TrustedDevice $device = null): RedirectResponse
    {
        if ($team instanceof TrustedDevice) {
            $device = $team;
        }
        abort_if(! $device, 404, 'Device not found.');

        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'Revoking devices is restricted to Administrator.');

        $device->update([
            'status' => 'revoked',
            'credential_hash' => null, // clear hash so credential immediately fails verification
        ]);

        // Revoke active sessions for the device's user(s) to enforce immediate lockout
        if ($device->user_id) {
            DB::table('sessions')->where('user_id', $device->user_id)->delete();
        }
        if ($device->is_shared && is_array($device->authorized_user_ids)) {
            DB::table('sessions')->whereIn('user_id', $device->authorized_user_ids)->delete();
        }

        AuditLogger::audit(
            'device.revoked',
            "Administrator {$currentUser->email} revoked device '{$device->device_name}' ({$device->device_uuid}). Workstation access immediately terminated.",
            $currentUser,
            $device->user,
            ['device_name' => $device->device_name, 'device_uuid' => $device->device_uuid],
            'success',
            $request
        );

        return back()->with('success', "Device '{$device->device_name}' revoked successfully. Workstation access is now blocked.");
    }

    /**
     * Approve a pending trusted device (Admin accessible).
     */
    public function approveDevice(Request $request, mixed $team, ?TrustedDevice $device = null): RedirectResponse
    {
        if ($team instanceof TrustedDevice) {
            $device = $team;
        }
        abort_if(! $device, 404, 'Device not found.');

        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'Approving devices is restricted to Administrator.');

        // For single-user devices, ensure 1 active device policy
        if ($device->user_id && ! $device->is_shared) {
            TrustedDevice::where('user_id', $device->user_id)
                ->where('id', '!=', $device->id)
                ->where('is_shared', false)
                ->delete();
        }

        $device->update([
            'status' => 'approved',
            'approved_by' => $currentUser->id,
            'approved_at' => now(),
            'last_used_at' => now(),
        ]);

        AuditLogger::audit(
            'device.approved',
            "Administrator {$currentUser->email} approved device '{$device->device_name}'.",
            $currentUser,
            $device->user,
            ['device_name' => $device->device_name],
            'success',
            $request
        );

        return back()->with('success', "Device '{$device->device_name}' approved successfully.");
    }

    /**
     * Delete a trusted device (Admin accessible).
     */
    public function destroyDevice(Request $request, mixed $team, ?TrustedDevice $device = null): RedirectResponse
    {
        if ($team instanceof TrustedDevice) {
            $device = $team;
        }
        abort_if(! $device, 404, 'Device not found.');

        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'Deleting devices is restricted to Administrator.');

        $deviceName = $device->device_name;
        $device->delete();

        AuditLogger::audit(
            'device.deleted',
            "Administrator {$currentUser->email} removed device '{$deviceName}'.",
            $currentUser,
            null,
            ['device_name' => $deviceName],
            'success',
            $request
        );

        return back()->with('success', 'Device record removed successfully.');
    }

    /**
     * Regenerate Emergency Admin Recovery Key (Admin accessible).
     */
    public function regenerateRecoveryKey(Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        abort_if(! $currentUser || ! $currentUser->isAdmin(), 403, 'Recovery key management is restricted to Administrator.');

        $newKey = 'RECOVER-' . strtoupper(Str::random(6)) . '-' . strtoupper(Str::random(6)) . '-' . strtoupper(Str::random(6));
        $currentUser->update(['admin_recovery_key' => $newKey]);

        AuditLogger::audit(
            'security.recovery_key_regenerated',
            "Administrator {$currentUser->email} regenerated their emergency admin recovery key.",
            $currentUser,
            $currentUser,
            null,
            'success',
            $request
        );

        return back()->with('success', "Emergency Admin Recovery Key regenerated: {$newKey}");
    }

    /**
     * Parse browser and operating system details from User-Agent string.
     */
    protected function parseAgent(?string $agent): array
    {
        if (! $agent) {
            return ['browser' => 'Unknown', 'os' => 'Unknown'];
        }

        $os = 'Unknown OS';
        if (str_contains($agent, 'Windows NT 10.0')) $os = 'Windows 10/11';
        elseif (str_contains($agent, 'Windows')) $os = 'Windows';
        elseif (str_contains($agent, 'Macintosh') || str_contains($agent, 'Mac OS X')) $os = 'macOS';
        elseif (str_contains($agent, 'Linux')) $os = 'Linux';
        elseif (str_contains($agent, 'Android')) $os = 'Android';
        elseif (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad')) $os = 'iOS';

        $browser = 'Unknown Browser';
        if (str_contains($agent, 'Edg/')) $browser = 'Microsoft Edge';
        elseif (str_contains($agent, 'Chrome/')) $browser = 'Google Chrome';
        elseif (str_contains($agent, 'Firefox/')) $browser = 'Mozilla Firefox';
        elseif (str_contains($agent, 'Safari/') && ! str_contains($agent, 'Chrome/')) $browser = 'Apple Safari';
        elseif (str_contains($agent, 'Opera') || str_contains($agent, 'OPR/')) $browser = 'Opera';

        return ['browser' => $browser, 'os' => $os];
    }
}
