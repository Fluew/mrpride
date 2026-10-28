<?php

namespace App\Models;

use App\Traits\TracksDeletions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderFulfillment extends Model
{
    use HasFactory, SoftDeletes, TracksDeletions;

    protected $table = 'order_fulfillments';

    public const MAX_ATTEMPTS = 3;

    protected $fillable = [
        'team_id',
        'sale_id',
        'commercial_invoice_id',
        'fulfillment_number',
        'fulfillment_type',
        'recipient_name',
        'contact_number',
        'delivery_address',
        'delivery_schedule',
        'instructions',
        'status',
        'attempt_count',
        'proof_of_delivery',
        'handled_by_id',
        'dispatched_at',
        'delivered_at',
        'rescheduled_at',
        'rescheduled_by_id',
        'notes',
        'cancellation_reason',
        'deleted_by_id',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'delivery_schedule' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'rescheduled_at' => 'datetime',
    ];

    protected $appends = [
        'proof_url',
        'remaining_attempts',
        'attempts_display',
    ];

    public function getProofUrlAttribute(): ?string
    {
        if (! $this->proof_of_delivery) {
            return null;
        }

        $team = $this->team;
        $slug = $team?->slug ?? 'mr-pride';

        return "/{$slug}/deliveries/fulfillments/{$this->id}/proof";
    }

    public function getRemainingAttemptsAttribute(): int
    {
        return max(0, self::MAX_ATTEMPTS - (int) $this->attempt_count);
    }

    public function getAttemptsDisplayAttribute(): string
    {
        return sprintf('%d/%d', min(self::MAX_ATTEMPTS, (int) $this->attempt_count), self::MAX_ATTEMPTS);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function commercialInvoice(): BelongsTo
    {
        return $this->belongsTo(CommercialInvoice::class);
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
    }

    public function rescheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescheduled_by_id');
    }

    public function attemptLogs(): HasMany
    {
        return $this->hasMany(DeliveryAttemptLog::class, 'order_fulfillment_id')->latest('id');
    }

    /**
     * Check if a delivery attempt can still be made.
     */
    public function canAttempt(): bool
    {
        return $this->attempt_count < self::MAX_ATTEMPTS
            && ! in_array($this->status, ['Delivered', 'Cancelled', 'Return to Warehouse', 'Maximum Attempts Reached'], true);
    }

    /**
     * Generate unique fulfillment tracking number (e.g., FUL-102826-001).
     */
    public static function generateFulfillmentNumber(int $teamId, mixed $date = null): string
    {
        $d = $date ? Carbon::parse($date) : Carbon::now();
        $dateStr = $d->format('mdy');
        $prefix = "FUL-{$dateStr}-";

        $count = static::withTrashed()
            ->where('team_id', $teamId)
            ->where('fulfillment_number', 'LIKE', "{$prefix}%")
            ->count();

        return $prefix.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
