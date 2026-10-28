<?php

return [
    /*
    | Public quotation request: material list upload.
    |
    | show_estimated_prices: when true, catalog selling prices are shown to clients (read-only) as
    | an *estimate* for lines matched to a product. Official prices are always set by staff.
    */
    'show_estimated_prices' => env('QUOTATION_SHOW_PRICES', true),

    // Default VAT used for estimates and for new quotation requests (same as staff quotations).
    'tax_rate' => 12.0,
];
