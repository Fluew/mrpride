<?php

namespace App\Observers;

use App\Models\Sale;
use App\Services\CustomerEmails;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Email the sales invoice to the customer when the sale is completed. */
class SaleEmailObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Sale $sale): void
    {
        if ($sale->status === 'Completed') {
            app(CustomerEmails::class)->saleInvoice($sale);
        }
    }

    public function updated(Sale $sale): void
    {
        if ($sale->wasChanged('status') && $sale->status === 'Completed') {
            app(CustomerEmails::class)->saleInvoice($sale);
        }
    }
}
