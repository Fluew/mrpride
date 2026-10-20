<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'request_number',
        'product_id',
        'supplier_id',
        'requested_by_id',
        'quantity',
        'estimated_cost',
        'status',
        'notes',
        'requested_at',
        'ordered_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'estimated_cost' => 'decimal:2',
            'requested_at' => 'datetime',
            'ordered_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * Generate the next purchase request number for a team.
     */
    public static function generateRequestNumber(int $teamId): string
    {
        $year = date('Y');
        $count = static::where('team_id', $teamId)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('PR-%s-%04d', substr($year, 2), $count + 1);
    }
}
