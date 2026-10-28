<?php

use App\Enums\TeamRole;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use App\Services\MaterialList\OcrEngine;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Inventory export (CSV, Excel, PDF, PNG, JPG) and import (PDF, Excel, CSV, JPG, PNG).
 */
function invActor(Team $team, string $role = 'staff_inventory'): array
{
    $user = User::factory()->create(['role' => $role, 'current_team_id' => $team->id, 'password_changed_at' => now()]);
    $team->members()->attach($user, ['role' => $role === 'admin' ? TeamRole::Owner->value : TeamRole::Member->value]);

    return [$user, createTrustedDeviceFor($user)['cookies']];
}

function invProduct(Team $team, array $attrs = []): Product
{
    static $n = 0;
    $n++;

    return Product::create($attrs + [
        'team_id' => $team->id, 'sku' => "SKU-{$n}", 'name' => "Product {$n}", 'category' => 'Wiring',
        'unit' => 'pcs', 'price' => 120.50, 'cost_price' => 80, 'stock' => 40, 'reorder_level' => 10, 'is_active' => true,
    ]);
}

function invXlsx(array $rows, string $name = 'stock.xlsx'): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray($rows);
    $path = tempnam(sys_get_temp_dir(), 'inv').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, $name, null, null, true);
}

function invPng(): UploadedFile
{
    $img = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($img);

    return UploadedFile::fake()->createWithContent('stock-photo.png', (string) ob_get_clean());
}

/** Fake Tesseract (and its version check) returning $text. */
function invFakeOcr(string $text): void
{
    Process::fake(fn (PendingProcess $p) => str_contains(implode(' ', (array) $p->command), '--version')
        ? Process::result('tesseract 5.4.0')
        : Process::result($text));
}

beforeEach(function () {
    $this->team = Team::factory()->create(['slug' => 'inv-team', 'name' => 'Inventory Team']);
    [$this->user, $this->cookies] = invActor($this->team);
});

// --- Export ---------------------------------------------------------------------------------

it('exports the inventory in every format', function (string $format, string $type, string $magic) {
    invProduct($this->team, ['name' => 'Circuit Breaker 60A', 'brand' => 'Schneider']);

    $res = $this->actingAs($this->user)->withCookies($this->cookies)
        ->get("/{$this->team->slug}/inventory/export?format={$format}")
        ->assertOk();

    expect($res->headers->get('Content-Type'))->toContain($type)
        ->and($res->headers->get('Content-Disposition'))->toContain(".{$format}")
        ->and($res->getContent() === false ? $res->streamedContent() : $res->getContent())->toStartWith($magic);
})->with([
    'csv' => ['csv', 'text/csv', "\xEF\xBB\xBFSKU,"],
    'excel' => ['xlsx', 'spreadsheetml', "PK\x03\x04"],
    'pdf' => ['pdf', 'application/pdf', '%PDF-'],
    'png' => ['png', 'image/png', "\x89PNG"],
    'jpg' => ['jpg', 'image/jpeg', "\xFF\xD8\xFF"],
]);

it('keeps the old CSV export address working', function () {
    invProduct($this->team, ['name' => 'Circuit Breaker 60A']);

    $csv = $this->actingAs($this->user)->withCookies($this->cookies)
        ->get("/{$this->team->slug}/inventory/export-csv")->assertOk()->streamedContent();

    expect($csv)->toContain('Circuit Breaker 60A');
});

it('exports only the products matching the current filters', function () {
    invProduct($this->team, ['name' => 'Plenty Wire', 'stock' => 500]);
    invProduct($this->team, ['name' => 'Almost Gone Tape', 'stock' => 3]);
    invProduct($this->team, ['name' => 'Other Team Item', 'team_id' => Team::factory()->create()->id]);

    $csv = $this->actingAs($this->user)->withCookies($this->cookies)
        ->get("/{$this->team->slug}/inventory/export?format=csv&status=Low+Stock")->assertOk()->streamedContent();

    expect($csv)->toContain('Almost Gone Tape')->not->toContain('Plenty Wire')->not->toContain('Other Team Item');
});

it('stores text as text in Excel so formulas never run', function () {
    invProduct($this->team, ['name' => '=HYPERLINK("http://evil.test","x")']);

    $content = $this->actingAs($this->user)->withCookies($this->cookies)
        ->get("/{$this->team->slug}/inventory/export?format=xlsx")->assertOk()->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'inv');
    file_put_contents($path, $content);
    $cell = IOFactory::load($path)->getActiveSheet()->getCell('B2');

    expect($cell->getDataType())->toBe('s')->and($cell->getValue())->toStartWith('=HYPERLINK');
});

it('splits large image exports into a zip of images', function () {
    foreach (range(1, 51) as $i) {
        invProduct($this->team);
    }

    $res = $this->actingAs($this->user)->withCookies($this->cookies)
        ->get("/{$this->team->slug}/inventory/export?format=png")->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'inv');
    file_put_contents($path, $res->getContent());
    $zip = new ZipArchive;
    $zip->open($path);

    expect($res->headers->get('Content-Type'))->toBe('application/zip')
        ->and($zip->numFiles)->toBe(2);
});

it('rejects unknown export formats', function () {
    $this->actingAs($this->user)->withCookies($this->cookies)
        ->get("/{$this->team->slug}/inventory/export?format=exe")
        ->assertSessionHasErrors('format');
});

// --- Import ---------------------------------------------------------------------------------

it('imports an Excel file that it exported (round trip)', function () {
    invProduct($this->team, ['sku' => 'BRK-60', 'name' => 'Circuit Breaker 60A', 'brand' => 'Schneider', 'stock' => 12, 'price' => 450]);

    $content = $this->actingAs($this->user)->withCookies($this->cookies)
        ->get("/{$this->team->slug}/inventory/export?format=xlsx")->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'inv').'.xlsx';
    file_put_contents($path, $content);

    $preview = $this->actingAs($this->user)->withCookies($this->cookies)
        ->post("/{$this->team->slug}/inventory/import-csv", ['file' => new UploadedFile($path, 'export.xlsx', null, null, true)])
        ->assertOk()->json();

    expect($preview['valid_count'])->toBe(1)
        ->and($preview['valid_rows'][0])->toMatchArray(['sku' => 'BRK-60', 'name' => 'Circuit Breaker 60A', 'brand' => 'Schneider', 'stock' => 12, 'price' => 450.0, 'is_update' => true])
        ->and($preview['warning'])->toBeNull();
});

it('finds the header row below a title and saves new products from Excel', function () {
    $file = invXlsx([
        ['Mr. Pride stock list - October'],
        [],
        ['SKU', 'Product Name', 'Category', 'Selling Price', 'Stock'],
        ['WR-35', 'THHN Wire 3.5mm', 'Wiring', 68, 25],
        ['', 'Missing SKU', 'Wiring', 10, 1],
    ]);

    $preview = $this->actingAs($this->user)->withCookies($this->cookies)
        ->post("/{$this->team->slug}/inventory/import-csv", ['file' => $file])->assertOk()->json();

    expect($preview['valid_count'])->toBe(1)
        ->and($preview['invalid_rows'][0])->toMatchArray(['row' => 5, 'errors' => ['SKU is required.']]);

    $this->actingAs($this->user)->withCookies($this->cookies)
        ->post("/{$this->team->slug}/inventory/import-csv", ['file' => $file, 'mode' => 'append', 'execute' => true])
        ->assertSessionHasNoErrors();

    expect(Product::where('team_id', $this->team->id)->where('sku', 'WR-35')->first())
        ->name->toBe('THHN Wire 3.5mm')->stock->toBe(25);
});

it('reads a photo of a stock list with OCR and fixes misread digits', function () {
    invFakeOcr(ocrTsv([
        'INVENTORY COUNT - Branch 2',
        'SKU      Product Name          Stock    Selling Price',
        'BRK-60   Circuit Breaker 60A      1O           450.00',
        'LED-9    LED Bulb 9W              l5               85',
        '                                         Page 1 of 1',
    ]));

    $res = $this->actingAs($this->user)->withCookies($this->cookies)
        ->post("/{$this->team->slug}/inventory/import-csv", ['file' => invPng()])->assertOk();

    expect($res->json('method'))->toBe('ocr')
        ->and($res->json('warning'))->toContain('text recognition')
        ->and($res->json('invalid_count'))->toBe(0)
        ->and(collect($res->json('valid_rows'))->map(fn ($r) => [$r['sku'], $r['name'], $r['stock'], $r['price']])->all())
        ->toEqual([['BRK-60', 'Circuit Breaker 60A', 10, 450], ['LED-9', 'LED Bulb 9W', 15, 85]]);
});

it('explains when a photo has no readable column headers', function () {
    invFakeOcr(ocrTsv(['just some blurry words', 'nothing useful']));

    $this->actingAs($this->user)->withCookies($this->cookies)
        ->post("/{$this->team->slug}/inventory/import-csv", ['file' => invPng()])
        ->assertStatus(422)
        ->assertJson(['success' => false])
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'column headers are clearly visible'));
});

it('rejects files that are not what their name says', function () {
    $fake = UploadedFile::fake()->createWithContent('stock.xlsx', 'this is not a spreadsheet');

    $this->actingAs($this->user)->withCookies($this->cookies)
        ->post("/{$this->team->slug}/inventory/import-csv", ['file' => $fake])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($m) => str_contains($m, "doesn't look like a valid .xlsx file"));
});

it('turns a read failure during the final import into a form error', function () {
    $fake = UploadedFile::fake()->createWithContent('stock.pdf', 'not a pdf');

    $this->actingAs($this->user)->withCookies($this->cookies)
        ->post("/{$this->team->slug}/inventory/import-csv", ['file' => $fake, 'mode' => 'append', 'execute' => true])
        ->assertSessionHasErrors('file');
});

// --- Round trips with the real tools (skipped when they are not installed) ------------------

it('imports a PDF it exported, keeping every column apart', function () {
    invProduct($this->team, ['sku' => 'BRK-60', 'name' => 'Molded Case Circuit Breaker 100A 3P', 'brand' => 'Schneider', 'stock' => 1200, 'price' => 1450, 'cost_price' => 980]);
    invProduct($this->team, ['sku' => 'TP-7', 'name' => 'Tape', 'stock' => 0, 'price' => 35]);

    $pdf = $this->actingAs($this->user)->withCookies($this->cookies)
        ->get("/{$this->team->slug}/inventory/export?format=pdf")->assertOk()->getContent();
    $file = UploadedFile::fake()->createWithContent('inventory.pdf', $pdf);

    $rows = $this->actingAs($this->user)->withCookies($this->cookies)
        ->post("/{$this->team->slug}/inventory/import-csv", ['file' => $file])->assertOk()->json('valid_rows');

    expect(collect($rows)->map(fn ($r) => [$r['sku'], $r['name'], $r['brand'], $r['stock'], $r['price'], $r['cost_price']])->all())->toEqual([
        ['TP-7', 'Tape', null, 0, 35, 80],
        ['BRK-60', 'Molded Case Circuit Breaker 100A 3P', 'Schneider', 1200, 1450, 980],
    ]);
})->skip(fn () => ! app(OcrEngine::class)->pdfTextAvailable(), 'Poppler (pdftotext) is not installed');

it('reads a PNG it exported with real OCR', function () {
    invProduct($this->team, ['sku' => 'BRK-60', 'name' => 'Circuit Breaker 60A', 'stock' => 25, 'price' => 450]);

    $png = $this->actingAs($this->user)->withCookies($this->cookies)
        ->get("/{$this->team->slug}/inventory/export?format=png")->assertOk()->getContent();
    $file = UploadedFile::fake()->createWithContent('inventory.png', $png);

    $rows = $this->actingAs($this->user)->withCookies($this->cookies)
        ->post("/{$this->team->slug}/inventory/import-csv", ['file' => $file])->assertOk()->json('valid_rows');

    expect($rows[0])->toMatchArray(['name' => 'Circuit Breaker 60A', 'stock' => 25, 'price' => 450.0]);
})->skip(fn () => ! app(OcrEngine::class)->tesseractAvailable(), 'Tesseract is not installed');
