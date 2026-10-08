<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

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
        'notes',
        'processed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
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
