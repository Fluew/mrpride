<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'invoice_date',
        'invoice_number',
        'quantity',
        'description',
        'unit_cost',
        'total_cost',
        'supplier_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Generate commercial invoice number formatted like CI-035-0722
     */
    public static function generateInvoiceNumber(int $teamId, mixed $date = null): string
    {
        $parsedDate = $date ? Carbon::parse($date) : now();
        $mmyy = $parsedDate->format('my'); // e.g. 0722, 1026

        $count = static::where('team_id', $teamId)->count() + 1;
        $counter = $count;

        do {
            $seq = str_pad((string) ($counter % 1000), 3, '0', STR_PAD_LEFT);
            $candidate = "CI-{$seq}-{$mmyy}";
            $counter++;
        } while (static::where('team_id', $teamId)->where('invoice_number', $candidate)->exists());

        return $candidate;
    }
}
