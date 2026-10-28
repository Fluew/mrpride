<?php

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function createTrustedDeviceFor(?User $user = null): array
{
    $rawCredential = Str::random(64);
    $device = TrustedDevice::create([
        'device_uuid' => (string) Str::uuid(),
        'device_name' => 'Test Authorized Device',
        'user_id' => $user?->id,
        'status' => 'approved',
        'credential_hash' => hash('sha256', $rawCredential),
        'approved_at' => now(),
    ]);

    return [
        'device' => $device,
        'raw_credential' => $rawCredential,
        'cookies' => [
            'mrpride_device_uuid' => $device->device_uuid,
            'mrpride_device_credential' => $rawCredential,
        ],
    ];
}

/**
 * Tesseract-style TSV for a text layout, for faking OCR in tests. Each character is 5px wide
 * (a space is about 0.4 of the 12px letter height, as in real text) and each line 30px tall, so columns can be
 * drawn with spaces; two or more spaces read as a column gap:
 *
 *     ocrTsv(["SKU      Product Name", "BRK-60   Circuit Breaker"])
 *
 * @param  array<int, string>  $lines
 */
function ocrTsv(array $lines): string
{
    $tsv = "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext\n";
    foreach ($lines as $n => $line) {
        preg_match_all('/\S+/u', $line, $words, PREG_OFFSET_CAPTURE);
        foreach ($words[0] as $i => [$word, $byte]) {
            $x = mb_strlen(substr($line, 0, $byte)) * 5;
            $tsv .= implode("\t", [5, 1, 1, 1, $n + 1, $i + 1, $x, 20 + $n * 30, mb_strlen($word) * 5, 12, 95, $word])."\n";
        }
    }

    return $tsv;
}
