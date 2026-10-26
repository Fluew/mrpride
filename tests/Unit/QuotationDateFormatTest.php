<?php

use App\Models\Quotation;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

test('quotation date and valid until are serialized as date only without iso time part', function () {
    $quotation = new Quotation([
        'quotation_date' => '2026-10-23',
        'valid_until' => '2026-11-22',
    ]);

    $array = $quotation->toArray();

    expect($array['quotation_date'])->toBe('2026-10-23')
        ->and($array['valid_until'])->toBe('2026-11-22')
        ->and($array['quotation_date'])->not->toContain('T')
        ->and($array['valid_until'])->not->toContain('T');

    $withTime = new Quotation([
        'quotation_date' => '2026-10-23 15:30:00',
        'valid_until' => '2026-11-22 23:59:59',
    ]);

    $timeArray = $withTime->toArray();
    expect($timeArray['quotation_date'])->toBe('2026-10-23')
        ->and($timeArray['valid_until'])->toBe('2026-11-22')
        ->and($timeArray['quotation_date'])->not->toContain('T')
        ->and($timeArray['valid_until'])->not->toContain('T');
});

test('generateNumber generates Q<MONTH><DAY><YEAR>-<PRODUCT_UNIQUE_ID> format correctly', function () {
    // Exact user example: Jan 04 2023 with SKU 068SSCR2 -> Q010423-068SSCR2
    $quoteNumber = Quotation::generateNumber(9999, '068SSCR2', '2023-01-04');
    expect($quoteNumber)->toBe('Q010423-068SSCR2');

    // Current date format: 10-25-2026 with SKU 068SSCR2 -> Q102526-068SSCR2
    $quoteNumber2 = Quotation::generateNumber(9999, '068SSCR2', '2026-10-25');
    expect($quoteNumber2)->toBe('Q102526-068SSCR2');
});

