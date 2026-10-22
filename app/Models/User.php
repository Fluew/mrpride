<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTeams;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $must_change_password
 * @property Carbon|null $password_changed_at
 * @property array|null $password_history
 * @property string|null $admin_recovery_key
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, TrustedDevice> $trustedDevices
 * @property-read Collection<int, AuditLog> $auditLogs
 */
#[Fillable([
    'name',
    'email',
    'role',
    'status',
    'password',
    'current_team_id',
    'must_change_password',
    'password_changed_at',
    'last_login_at',
    'password_history',
    'admin_recovery_key',
])]
#[Hidden([
    'password',
    'password_history',
    'admin_recovery_key',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_history' => 'array',
        ];
    }

    /**
     * All recognized staff roles mapped to their module name.
     */
    public const STAFF_ROLES = [
        'staff_inventory' => 'Inventory',
        'staff_quotations' => 'Quotations',
        'staff_procurement' => 'Procurement',
        'staff_sales' => 'Sales',
        'staff_forecast' => 'Demand Forecasting',
        'staff_suppliers' => 'Suppliers',
        'staff_reports' => 'Reports & Analytics',
    ];

    /**
     * Determine if user has administrator privileges (replaces superadmin).
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Determine if user has administrator privileges (Admin can access all modules).
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin', 'owner'], true);
    }

    /**
     * Determine if user has strictly regular admin role.
     */
    public function isRegularAdmin(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Determine if user has staff privileges (1 module per staff role).
     */
    public function isStaff(): bool
    {
        return str_starts_with($this->role, 'staff_') || $this->role === 'staff';
    }

    /**
     * Get the route slug of the single module assigned to this staff member.
     */
    public function assignedModuleSlug(): string
    {
        if ($this->isAdmin()) {
            return 'dashboard';
        }

        return match ($this->role) {
            'staff_quotations' => 'quotations',
            'staff_procurement' => 'procurement',
            'staff_sales' => 'sales',
            'staff_forecast' => 'forecast',
            'staff_suppliers' => 'suppliers',
            'staff_reports' => 'reports',
            default => 'inventory',
        };
    }

    /**
     * Get human-friendly label for the role.
     */
    public function roleLabel(): string
    {
        if ($this->isAdmin()) {
            return 'Administrator';
        }

        if (isset(self::STAFF_ROLES[$this->role])) {
            return 'Staff (' . self::STAFF_ROLES[$this->role] . ')';
        }

        if ($this->role === 'staff') {
            return 'Staff (Inventory)';
        }

        return 'User';
    }

    /**
     * Determine if user is a standard customer.
     */
    public function isUser(): bool
    {
        return $this->role === 'user' || empty($this->role);
    }

    /**
     * Determine if user is internal personnel (admin or staff).
     */
    public function isAdminOrStaff(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    /**
     * Check if user account is active.
     */
    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    /**
     * Determine if user has any of the given roles.
     *
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $rolesArray = is_array($roles) ? $roles : func_get_args();

        return in_array($this->role, $rolesArray, true);
    }

    /**
     * Check if the user's password has reached the 90-day expiration threshold.
     */
    public function isPasswordExpired(): bool
    {
        // Only enforced for admin and staff accounts
        if (! $this->isAdminOrStaff()) {
            return false;
        }

        if ($this->must_change_password) {
            return true;
        }

        if (! $this->password_changed_at) {
            return true;
        }

        // Expire after 90 days (3 months)
        return $this->password_changed_at->diffInDays(now()) >= 90;
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
