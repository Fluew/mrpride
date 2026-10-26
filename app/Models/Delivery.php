<?php

namespace App\Models;

use App\Traits\TracksDeletions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory, TracksDeletions;

    protected $fillable = [
        'team_id',
        'delivery_date',
        'delivery_number',
        'purchase_order_number',
        'purchase_order_id',
        'quantity',
        'unit',
        'description',
        'supplier_id',
        'received_by_id',
        'status',
        'notes',
        'deleted_by_id',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'quantity' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_id');
    }

    /**
     * Generate unique delivery number, e.g. DEL-102526-001
     */
    public static function generateDeliveryNumber(int $teamId, mixed $date = null): string
    {
        $parsedDate = $date ? Carbon::parse($date) : now();
        $dateCode = $parsedDate->format('mdy'); // e.g. 102526

        $count = static::where('team_id', $teamId)
            ->whereDate('delivery_date', $parsedDate->toDateString())
            ->count() + 1;

        $counter = $count;
        do {
            $seq = str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
            $candidate = "DEL-{$dateCode}-{$seq}";
            $counter++;
        } while (static::where('team_id', $teamId)->where('delivery_number', $candidate)->exists());

        return $candidate;
    }
}
