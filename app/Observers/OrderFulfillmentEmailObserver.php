<?php

namespace App\Observers;

use App\Models\OrderFulfillment;
use App\Services\CustomerEmails;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Tell the customer their order is in transit. The Deliveries page calls this "Out for Delivery"
 * and the Sales page's fulfillment buttons call it "Dispatched"; either one sends the notice once.
 */
class OrderFulfillmentEmailObserver implements ShouldHandleEventsAfterCommit
{
    public const IN_TRANSIT = ['Out for Delivery', 'Dispatched'];

    public function updated(OrderFulfillment $fulfillment): void
    {
        if (! $fulfillment->wasChanged('status') || ! in_array($fulfillment->status, self::IN_TRANSIT, true)) {
            return;
        }

        // Dispatched -> Out for Delivery (or back) is the same trip: don't email twice.
        if (in_array($fulfillment->getOriginal('status'), self::IN_TRANSIT, true)) {
            return;
        }

        app(CustomerEmails::class)->outForDelivery($fulfillment);
    }
}
