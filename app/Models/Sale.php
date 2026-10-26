<?php

namespace App\Models;

use App\Traits\TracksDeletions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'payment_date',
        'proof_of_payment',
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
            'payment_date' => 'datetime',
        ];
    }

    protected $appends = [
        'proof_url',
    ];

    public function getProofUrlAttribute(): ?string
    {
        return $this->proof_of_payment ? asset('storage/' . $this->proof_of_payment) : null;
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
