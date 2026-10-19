<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'request_id',
        'product_id',
        'supplier_id',
        'item_name',
        'supplier_name',
        'quantity',
        'status',
        'date_requested',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_requested' => 'datetime',
            'quantity' => 'integer',
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

    public static function generateRequestId(int $teamId): string
    {
        $count = static::where('team_id', $teamId)->count();
        return sprintf('PR-%05d', 10001 + $count);
    }
}
