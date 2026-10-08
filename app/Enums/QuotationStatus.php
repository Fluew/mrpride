<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'Draft';
    case Pending = 'Pending';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Cancelled = 'Cancelled';
    case Expired = 'Expired';
    case Completed = 'Completed';

    /**
     * Get user-friendly label.
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Get badge styling classes for UI.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-700 border-gray-200',
            self::Pending => 'bg-[#F8B803]/15 text-[#4b5320] border-[#F8B803]/30 font-semibold',
            self::Approved => 'bg-emerald-50 text-emerald-700 border-emerald-200 font-semibold',
            self::Rejected => 'bg-rose-50 text-rose-700 border-rose-200',
            self::Cancelled => 'bg-slate-100 text-slate-600 border-slate-200 line-through',
            self::Expired => 'bg-amber-50 text-amber-700 border-amber-200',
            self::Completed => 'bg-blue-50 text-blue-700 border-blue-200 font-semibold',
        };
    }

    /**
     * Check if quotation can be converted to sale.
     */
    public function canConvertToSale(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Check if quotation is editable.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Pending]);
    }
}
