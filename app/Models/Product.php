<?php

namespace App\Models;

use App\Traits\TracksDeletions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, TracksDeletions;

    protected $fillable = [
        'team_id',
        'sku',
        'name',
        'category',
        'brand',
        'unit',
        'price',
        'cost_price',
        'stock',
        'min_stock',
        'reorder_level',
        'reorder_quantity',
        'status',
        'description',
        'supplier_id',
        'is_active',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'stock' => 'integer',
            'min_stock' => 'integer',
            'reorder_level' => 'integer',
            'reorder_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('movement_date');
    }

    /**
     * Calculate and update product status based on stock and reorder level.
     */
    public function updateCalculatedStatus(): void
    {
        $threshold = $this->reorder_level > 0 ? $this->reorder_level : ($this->min_stock > 0 ? $this->min_stock : 10);

        if ($this->stock <= 0) {
            $this->status = 'Out of Stock';
        } elseif ($this->stock <= $threshold) {
            $this->status = 'Low Stock';
        } else {
            $this->status = 'In Stock';
        }

        // Keep min_stock in sync with reorder_level
        $this->min_stock = $threshold;
        $this->reorder_level = $threshold;

        $this->save();
    }
}
