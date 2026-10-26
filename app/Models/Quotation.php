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
            'quotation_date' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
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
     * Generate quotation number in format: Q<MONTH><DAY><YEAR>-<PRODUCT_UNIQUE_ID>
     * Example: Q010423-068SSCR2
     */
    public static function generateNumber(int $teamId, ?string $productSku = null, mixed $date = null): string
    {
        $carbonDate = $date ? \Illuminate\Support\Carbon::parse($date) : now();
        $datePart = $carbonDate->format('mdy'); // 2-digit month, 2-digit day, 2-digit year (e.g. 010423 or 102526)
        $skuPart = !empty($productSku)
            ? preg_replace('/[^A-Za-z0-9]/', '', strtoupper($productSku))
            : 'PROD';

        $baseNumber = sprintf('Q%s-%s', $datePart, $skuPart);
        $candidate = $baseNumber;
        $counter = 1;

        while (static::where('team_id', $teamId)->where('quotation_number', $candidate)->exists()) {
            $candidate = sprintf('%s-%02d', $baseNumber, $counter);
            $counter++;
        }

        return $candidate;
    }

    /**
     * Check if quotation is currently expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (! $this->valid_until) {
            return false;
        }

        $validUntil = $this->valid_until instanceof \Carbon\CarbonInterface
            ? $this->valid_until->toDateString()
            : substr((string) $this->valid_until, 0, 10);

        return $validUntil < now()->toDateString() && ! in_array($this->status, [QuotationStatus::Completed, QuotationStatus::Cancelled]);
    }
}
