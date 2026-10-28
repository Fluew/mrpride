<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use App\Traits\TracksDeletions;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Quotation extends Model
{
    use HasFactory, TracksDeletions;

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
        'attachment_path',
        'material_file_path',
        'material_file_name',
        'source_file_sha256',
        'request_details',
        'prepared_by_id',
        'converted_to_sale_id',
        'deleted_by_id',
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
            'request_details' => 'array',
        ];
    }

    /** File fingerprints are only used on the server, to spot repeated imports. */
    protected $hidden = ['source_file_sha256'];

    protected $appends = [
        'attachment_url',
        'material_file_url',
    ];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->attachment_path ? route('quotations.attachment', ['quotation' => $this->id]) : null;
    }

    /** Staff-only link to the client's original material-list file (PDF/Excel/CSV/image). */
    public function getMaterialFileUrlAttribute(): ?string
    {
        return $this->material_file_path ? route('quotations.attachment', ['quotation' => $this->id, 'file' => 'materials']) : null;
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

    /** Extra files the client sent with the request (product lists). */
    public function files(): HasMany
    {
        return $this->hasMany(QuotationFile::class);
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
        $carbonDate = $date ? Carbon::parse($date) : now();
        $datePart = $carbonDate->format('mdy'); // 2-digit month, 2-digit day, 2-digit year (e.g. 010423 or 102526)
        $skuPart = ! empty($productSku)
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

        $validUntil = $this->valid_until instanceof CarbonInterface
            ? $this->valid_until->toDateString()
            : substr((string) $this->valid_until, 0, 10);

        return $validUntil < now()->toDateString() && ! in_array($this->status, [QuotationStatus::Completed, QuotationStatus::Cancelled]);
    }
}
