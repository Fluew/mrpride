<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'quotation_number',
        'client_id',
        'client_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'quotation_date',
        'valid_until',
        'subtotal',
        'discount',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'status',
        'notes',
        'terms_and_conditions',
        'prepared_by_id',
        'converted_to_sale_id',
    ];

    protected function casts(): array
    {
        return [
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'status' => QuotationStatus::class,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'converted_to_sale_id');
    }

    /**
     * Generate next sequential quotation number for team.
     */
    public static function generateNumber(int $teamId): string
    {
        $year = date('Y');
        $count = static::where('team_id', $teamId)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('QT-%s-%04d', $year, $count + 1);
    }

    /**
     * Check if quotation is currently expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->valid_until < now()->toDateString() && !in_array($this->status, [QuotationStatus::Completed, QuotationStatus::Cancelled]);
    }
}
