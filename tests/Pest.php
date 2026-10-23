<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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

function createTrustedDeviceFor(?\App\Models\User $user = null): array
{
    $rawCredential = \Illuminate\Support\Str::random(64);
    $device = \App\Models\TrustedDevice::create([
        'device_uuid' => (string) \Illuminate\Support\Str::uuid(),
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

