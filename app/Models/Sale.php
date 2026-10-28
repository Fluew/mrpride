<?php

namespace App\Models;

use App\Traits\TracksDeletions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use HasFactory, TracksDeletions;

    protected $fillable = [
        'team_id',
        'invoice_number',
        'quotation_id',
        'client_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'sale_date',
        'subtotal',
        'discount',
        'tax_amount',
        'grand_total',
        'status',
        'payment_method',
        'payment_reference',
        'amount_paid',
        'outstanding_balance',
        'payment_date',
        'payment_status',
        'proof_of_payment',
        'verified_by_id',
        'verified_at',
        'notes',
        'processed_by_id',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'outstanding_balance' => 'decimal:2',
            'payment_date' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    protected $appends = [
        'proof_url',
    ];

    public function getProofUrlAttribute(): ?string
    {
        // Authenticated, team-checked route (current_team comes from the URL defaults set per request).
        return $this->proof_of_payment ? route('sales.proof', ['sale' => $this->id]) : null;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function fulfillment(): HasOne
    {
        return $this->hasOne(OrderFulfillment::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_id');
    }

    public static function generateInvoiceNumber(int $teamId): string
    {
        $year = date('Y');
        $count = static::where('team_id', $teamId)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('INV-%s-%04d', $year, $count + 1);
    }
}
