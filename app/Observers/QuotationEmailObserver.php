<?php

namespace App\Observers;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Services\CustomerEmails;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Email the quotation to the client once it is approved (i.e. final). */
class QuotationEmailObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Quotation $quotation): void
    {
        if ($quotation->status === QuotationStatus::Approved) {
            app(CustomerEmails::class)->quotationApproved($quotation);
        }
    }

    public function updated(Quotation $quotation): void
    {
        if ($quotation->wasChanged('status') && $quotation->status === QuotationStatus::Approved) {
            app(CustomerEmails::class)->quotationApproved($quotation);
        }
    }
}
