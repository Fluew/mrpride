<?php

use App\Enums\QuotationStatus;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Team;
use App\Services\MaterialList\HeaderMapper;
use App\Services\MaterialList\MaterialParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Public quotation form: material list upload, extraction, review data and submission.
 */
beforeEach(function () {
    Storage::fake('local');
    $this->team = Team::factory()->create();
    $this->breaker = Product::create([
        'team_id' => $this->team->id, 'sku' => 'BRK-60A-SCH', 'name' => 'Circuit Breaker 60A', 'brand' => 'Schneider',
        'category' => 'Breakers', 'unit' => 'pcs', 'price' => 850.00, 'stock' => 20, 'is_active' => true,
    ]);
    $this->wire = Product::create([
        'team_id' => $this->team->id, 'sku' => 'THHN-35', 'name' => 'THHN Wire 3.5mm', 'brand' => 'Phelps Dodge',
        'category' => 'Wires', 'unit' => 'roll', 'price' => 2400.00, 'stock' => 10, 'is_active' => true,
    ]);
});

function extractUpload(UploadedFile $file)
{
    return test()->postJson('/quotation/materials/extract', ['file' => $file]);
}

function csvFile(string $content, string $name = 'materials.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

function xlsxFile(array $rows, string $name = 'materials.xlsx'): UploadedFile
{
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
    $book->getActiveSheet()->setCellValue('C5', '=2*5'); // a formula: we read its saved value, never run it
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, $name, null, null, true);
}

/** Smallest valid PDF with one text line per entry (Helvetica), so the parser has a real text layer. */
function pdfFile(array $lines, string $name = 'materials.pdf'): UploadedFile
{
    $y = 780;
    $stream = "BT /F1 11 Tf\n";
    foreach ($lines as $line) {
        $stream .= sprintf("1 0 0 1 50 %d Tm (%s) Tj\n", $y, str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line));
        $y -= 18;
    }
    $stream .= 'ET';
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $o) {
        $pdf .= sprintf("%010d 00000 n \n", $o);
    }
    $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    return UploadedFile::fake()->createWithContent($name, $pdf);
}

/** A real (tiny) PNG, so content checks pass; OCR itself is faked. */
function pngFile(): UploadedFile
{
    $img = imagecreatetruecolor(40, 20);
    ob_start();
    imagepng($img);
    $png = ob_get_clean();

    return UploadedFile::fake()->createWithContent('photo.png', $png);
}

function fakeTesseract(?string $text): void
{
    Process::fake(function (PendingProcess $process) use ($text) {
        $cmd = implode(' ', (array) $process->command);
        if ($text === null) {
            return Process::result('', 'not found', 1);
        }

        return str_contains($cmd, '--version') ? Process::result('tesseract 5.3.0') : Process::result($text);
    });
}

// --- Header recognition ---------------------------------------------------------------------

it('recognises header variations regardless of case and punctuation', function () {
    expect(HeaderMapper::fieldFor(' QTY. '))->toBe('quantity')
        ->and(HeaderMapper::fieldFor('Amount Required'))->toBe('quantity')
        ->and(HeaderMapper::fieldFor('Item Description'))->toBe('description')
        ->and(HeaderMapper::fieldFor('U.O.M'))->toBe('unit')
        ->and(HeaderMapper::fieldFor('Unit of Measure'))->toBe('unit')
        ->and(HeaderMapper::fieldFor('Preferred Brand'))->toBe('brand')
        ->and(HeaderMapper::fieldFor('Technical Details'))->toBe('specs')
        ->and(HeaderMapper::fieldFor('Unit Price'))->toBe('unit_price')
        ->and(HeaderMapper::fieldFor('Qty (pcs)'))->toBe('quantity')
        ->and(HeaderMapper::fieldFor('Supplier Phone'))->toBeNull();
});

// --- CSV ------------------------------------------------------------------------------------

it('reads a CSV with its own column order, semicolons and extra columns', function () {
    $csv = "Project: Warehouse fit-out\n\nNo.;Preferred Brand;Item Description;QTY.;U.O.M;Supplier Phone;Remarks\n"
        ."1;Schneider;Circuit breaker BRK-60A-SCH;10;pcs;0917;urgent\n"
        ."2;Phelps Dodge;THHN wire 3.5mm;5;rolls;0917;\n"
        ."3;;LED panel light 600x600;abc;pcs;;\n";

    $res = extractUpload(csvFile($csv))->assertOk();

    expect($res->json('method'))->toBe('csv')->and($res->json('confident'))->toBeTrue();
    $items = $res->json('items');
    expect($items)->toHaveCount(3);
    expect($items[0])->toMatchArray(['description' => 'Circuit breaker BRK-60A-SCH', 'quantity' => 10, 'unit' => 'pcs', 'brand' => 'Schneider', 'remarks' => 'urgent']);
    expect($items[0]['match']['product_id'])->toBe($this->breaker->id);   // exact code → matched
    expect($items[1]['match'])->toBeNull();                              // name only → suggestion
    expect(collect($items[1]['suggestions'])->pluck('product_id'))->toContain($this->wire->id);
    expect($items[2]['errors'])->toContain('Quantity "abc" is not a valid number.'); // kept, flagged
});

it('reads Windows-1252 CSV files without garbling the text', function () {
    $csv = mb_convert_encoding("Description,Qty\nCañería conduit 20mm,12\n", 'Windows-1252', 'UTF-8');

    expect(extractUpload(csvFile($csv))->assertOk()->json('items.0.description'))->toBe('Cañería conduit 20mm');
});

it('asks the client to map the columns when headers are not recognised, then reads them', function () {
    $csv = "Thing,How many,Who\nBreaker 60A,4,Ana\nLED bulb,20,Ben\n";

    $res = extractUpload(csvFile($csv))->assertOk();
    expect($res->json('confident'))->toBeFalse()
        ->and($res->json('items'))->toBe([])
        ->and($res->json('columns'))->toBe(['Thing', 'How many', 'Who']);

    $mapped = $this->postJson('/quotation/materials/remap', [
        'token' => $res->json('token'),
        'mapping' => ['description' => 0, 'quantity' => 1],
        'header_index' => 0,
    ])->assertOk();

    expect($mapped->json('items'))->toHaveCount(2)
        ->and($mapped->json('items.1'))->toMatchArray(['description' => 'LED bulb', 'quantity' => 20]);
});

it('flags duplicate lines but keeps them', function () {
    $res = extractUpload(csvFile("Item,Qty\nLED bulb,10\nLED bulb,10\n"))->assertOk();

    expect($res->json('items'))->toHaveCount(2)
        ->and($res->json('items.1.warnings.0'))->toContain('duplicate');
});

// --- Excel ----------------------------------------------------------------------------------

it('reads an Excel sheet with a title row, and never runs formulas', function () {
    $file = xlsxFile([
        ['MATERIAL REQUEST - BLDG A'],
        [],
        ['Product Name', 'Specification', 'Quantity', 'Unit'],
        ['Circuit Breaker 60A', 'Bolt-on', 3, 'pcs'],
        ['THHN Wire 3.5mm', 'Stranded', 0, 'roll'], // quantity replaced by formula =2*5 (saved value 10)
    ]);

    $res = extractUpload($file)->assertOk();

    expect($res->json('method'))->toBe('spreadsheet');
    expect($res->json('items.0'))->toMatchArray(['description' => 'Circuit Breaker 60A', 'specs' => 'Bolt-on', 'quantity' => 3]);
    expect($res->json('items.1.quantity'))->toEqual(10);
});

// --- PDF ------------------------------------------------------------------------------------

it('reads the text layer of a PDF without OCR', function () {
    fakeTesseract(null); // OCR unavailable: must not be needed for text PDFs
    $file = pdfFile([
        'ABC Contractors - Material List',
        '10 pcs Circuit Breaker 60A Schneider',
        'LED panel light 600x600 - 24 pcs',
        'Please deliver to site office',
        'Page 1 of 1',
    ]);

    $res = extractUpload($file)->assertOk();

    expect($res->json('method'))->toBe('pdf-text');
    $items = collect($res->json('items'));
    expect($items->pluck('quantity')->all())->toEqual([10, 24])
        ->and($items->pluck('description')->all())->toBe(['Circuit Breaker 60A Schneider', 'LED panel light 600x600'])
        ->and($items->every(fn ($i) => $i['needs_review']))->toBeTrue();
    // Lines without a quantity are offered to the client, not turned into items or dropped.
    expect($res->json('unparsed_lines'))->toContain('Please deliver to site office')
        ->not->toContain('Page 1 of 1');
});

// --- Images (OCR) ---------------------------------------------------------------------------

it('runs Tesseract on images and parses the recognised text', function () {
    fakeTesseract("MATERIALS\n1O pcs Circuit Breaker 60A\nLED bulb x 20\nThank you\n");

    $res = extractUpload(pngFile())->assertOk();

    expect($res->json('method'))->toBe('ocr');
    expect(collect($res->json('items'))->pluck('quantity')->all())->toEqual([10, 20]); // "1O" (OCR) → 10
});

it('ignores stray OCR marks after the unit', function () {
    // Real Tesseract output for "25 m  THHN Wire" in a column layout.
    fakeTesseract('25 m_— THHN Wire 3.5mm
4 box ~ Electrical Tape
');

    $res = extractUpload(pngFile())->assertOk();

    expect(collect($res->json('items'))->map(fn ($i) => [$i['quantity'], $i['unit'], $i['description']])->all())
        ->toEqual([[25, 'm', 'THHN Wire 3.5mm'], [4, 'box', 'Electrical Tape']]);
});

it('explains when OCR is not available', function () {
    fakeTesseract(null);

    extractUpload(pngFile())->assertStatus(422)
        ->assertJsonPath('reason', 'ocr_unavailable')
        ->assertJson(['message' => 'Text recognition (OCR) is not available on the server right now. Please type your material list below instead.']);
});

// --- Bad files ------------------------------------------------------------------------------

it('rejects unsupported, fake, empty and oversized files with clear messages', function () {
    extractUpload(UploadedFile::fake()->createWithContent('list.txt', "Item,Qty\nA,1"))->assertStatus(422)
        ->assertJsonValidationErrors(['file' => 'not supported']);

    extractUpload(UploadedFile::fake()->createWithContent('list.pdf', 'this is not a pdf'))->assertStatus(422)
        ->assertJsonPath('reason', 'corrupted');

    extractUpload(UploadedFile::fake()->createWithContent('photo.jpg', 'not an image'))->assertStatus(422)
        ->assertJsonPath('reason', 'corrupted');

    extractUpload(UploadedFile::fake()->createWithContent('list.xlsx', 'PK not really'))->assertStatus(422)
        ->assertJsonPath('reason', 'corrupted');

    extractUpload(UploadedFile::fake()->create('big.csv', 10241))->assertStatus(422)
        ->assertJsonValidationErrors(['file' => '10 MB']);

    // Nothing invalid is kept on disk.
    expect(Storage::disk('local')->files('quotations/material-lists'))->toBe([]);
});

it('stores uploads under random names and ignores tokens from other sessions', function () {
    $res = extractUpload(csvFile("Item,Qty\nA,1\n", '..\\..\\evil.php.csv'))->assertOk();
    $stored = Storage::disk('local')->files('quotations/material-lists');
    expect($stored)->toHaveCount(1)->and($stored[0])->not->toContain('evil');

    $this->postJson('/quotation/materials/remap', ['token' => 'someone-elses-token', 'mapping' => ['description' => 0, 'quantity' => 1]])
        ->assertStatus(410);
});

// --- Text cleaning --------------------------------------------------------------------------

it('removes page numbers and running headers but keeps real lines', function () {
    $lines = MaterialParser::cleanLines([
        "ACME Corp Confidential\n• 5 rolls THHN 3.5mm\nPage 1 of 2",
        "ACME Corp Confidential\n- 2 pcs Panel board 12-way\n2",
    ]);

    expect($lines)->toBe(['5 rolls THHN 3.5mm', '2 pcs Panel board 12-way']);
});

// --- Submitting the quotation request ------------------------------------------------------

function submitQuotation(array $materials, array $extra = [])
{
    return test()->post('/quotation', $extra + [
        'company' => 'Apex Builders', 'contactName' => 'Ana Reyes', 'email' => 'ana@apex.example', 'phone' => '0917',
        'materials' => $materials, 'materials_confirmed' => true,
    ]);
}

it('creates quotation items with server-side prices and totals, and keeps the original file', function () {
    $token = extractUpload(csvFile("Item,Qty\nBreaker,1\n"))->json('token');

    submitQuotation([
        // Client tries to send its own price/total: ignored.
        ['description' => 'Circuit breaker', 'quantity' => 4, 'unit' => 'pcs', 'product_id' => $this->breaker->id, 'unit_price' => 1, 'subtotal' => 1],
        ['description' => 'Special conduit fitting', 'quantity' => 3, 'brand' => 'Unknown', 'specs' => '25mm'],
    ], ['material_token' => $token])->assertSessionHasNoErrors();

    $q = Quotation::latest('id')->firstOrFail();
    $items = $q->items()->orderBy('id')->get();
    expect($items)->toHaveCount(2);
    expect($items[0])->product_id->toBe($this->breaker->id)->sku->toBe('BRK-60A-SCH')
        ->and((float) $items[0]->unit_price)->toBe(850.0)
        ->and((float) $items[0]->subtotal)->toBe(3400.0);
    expect($items[1])->product_id->toBeNull()->sku->toBe('CUSTOM')
        ->and($items[1]->product_name)->toBe('Special conduit fitting — Unknown (25mm)')
        ->and((float) $items[1]->unit_price)->toBe(0.0);

    expect((float) $q->subtotal)->toBe(3400.0)
        ->and((float) $q->tax_amount)->toBe(408.0)
        ->and((float) $q->grand_total)->toBe(3808.0)
        ->and($q->status)->toBe(QuotationStatus::Pending)
        ->and($q->notes)->toContain('1 item(s) need pricing by staff');

    expect($q->material_file_name)->toBe('materials.csv');
    Storage::disk('local')->assertExists($q->material_file_path);
    expect(Storage::disk('local')->files('quotations/material-lists'))->toBe([]);
});

it('does not require a brand when a material list is given', function () {
    submitQuotation([['description' => 'LED bulb', 'quantity' => 2]])->assertSessionHasNoErrors();

    expect(Quotation::count())->toBe(1);
});

it('requires confirmation and valid lines', function () {
    submitQuotation([['description' => 'LED bulb', 'quantity' => 2]], ['materials_confirmed' => false])
        ->assertSessionHasErrors('materials_confirmed');
    submitQuotation([['description' => 'LED bulb', 'quantity' => 0]])->assertSessionHasErrors('materials.0.quantity');
    submitQuotation([['description' => 'LED bulb', 'quantity' => 2.5]])->assertSessionHasErrors('materials.0.quantity');
    submitQuotation([['description' => '', 'quantity' => 2]])->assertSessionHasErrors('materials.0.description');

    expect(Quotation::count())->toBe(0);
});

it('treats an inactive or unknown product as a custom item', function () {
    $this->breaker->update(['is_active' => false]);

    submitQuotation([['description' => 'Breaker', 'quantity' => 1, 'product_id' => $this->breaker->id]])->assertSessionHasNoErrors();

    $item = Quotation::latest('id')->first()->items()->first();
    expect($item->product_id)->toBeNull()->and((float) $item->unit_price)->toBe(0.0);
});

it('no longer accepts a brand-only request: the material list is required', function () {
    $this->post('/quotation', [
        'company' => 'Apex Builders', 'contactName' => 'Ana', 'email' => 'ana@apex.example', 'phone' => '0917',
        'hasUnlistedBrands' => true, 'unlistedBrands' => 'Hager',
    ])->assertSessionHasErrors('materials');

    expect(Quotation::count())->toBe(0);
});
