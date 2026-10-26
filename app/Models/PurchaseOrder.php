<?php

namespace App\Models;

use App\Traits\TracksDeletions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    use HasFactory, TracksDeletions;

    protected $fillable = [
        'team_id',
        'po_date',
        'po_number',
        'quantity',
        'item_sku',
        'description',
        'unit_of_measure',
        'unit_cost',
        'total_cost',
        'supplier_id',
        'status',
        'notes',
        'deleted_by_id',
    ];

    protected $casts = [
        'po_date' => 'date',
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
     * Generate purchase order number formatted like 4000005472 (10-digit number)
     */
    public static function generatePoNumber(int $teamId): string
    {
        $base = 4000000000;
        $count = static::where('team_id', $teamId)->count();
        $candidateNum = $base + 5000 + $count + 1;

        while (static::where('team_id', $teamId)->where('po_number', (string) $candidateNum)->exists()) {
            $candidateNum++;
        }

        return (string) $candidateNum;
    }
}
