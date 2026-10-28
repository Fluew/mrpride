<?php

use App\Enums\QuotationStatus;
use App\Enums\TeamRole;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationFile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The quotation request workflow: the required material list, brands and product-list files on
 * the public form, and what Quotation Staff can see, open and import on the management page.
 */
beforeEach(function () {
    Storage::fake('local');
    $this->team = Team::factory()->create(['slug' => 'mr-pride']);
    Brand::firstOrCreate(['name' => 'Schneider Electric'], ['is_active' => true]);
});

function wfStaff(Team $team, string $role = 'staff_quotations'): array
{
    $user = User::factory()->create(['role' => $role, 'current_team_id' => $team->id, 'password_changed_at' => now()]);
    $team->members()->attach($user, ['role' => $role === 'admin' ? TeamRole::Owner->value : TeamRole::Member->value]);

    return [$user, createTrustedDeviceFor($user)['cookies']];
}

function wfRequest(array $overrides = []): array
{
    return array_merge([
        'company' => 'Acme Builders',
        'contactName' => 'Juan Dela Cruz',
        'email' => 'juan@acme.test',
        'phone' => '09171234567',
        'projectType' => 'Industrial Facility',
        'timeline' => '1-3 months',
    ], $overrides);
}

function wfPng(string $name = 'products.png', int $seed = 1): UploadedFile
{
    $img = imagecreatetruecolor(20 + $seed, 20);
    ob_start();
    imagepng($img);

    return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
}

function wfPdf(string $name = 'products.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << >> endobj\ntrailer << >>\n%%EOF\n");
}

// --- 1-2. The material list is required ----------------------------------------------------

it('refuses a request without a material list, even with brands, and cannot be bypassed', function () {
    $this->post('/quotation', wfRequest(['brands' => [['brand' => 'Schneider Electric', 'details' => '']]]))
        ->assertSessionHasErrors('materials');

    // Empty lines, an unconfirmed list, or a token that is not from this session do not count.
    $this->post('/quotation', wfRequest(['materials' => []]))->assertSessionHasErrors('materials');
    $this->post('/quotation', wfRequest(['materials' => [['description' => 'Breaker', 'quantity' => 2]]]))->assertSessionHasErrors('materials_confirmed');
    $this->post('/quotation', wfRequest(['material_token' => str_repeat('a', 40)]))->assertSessionHasErrors('materials');

    expect(Quotation::count())->toBe(0);
});

it('accepts a material list entered manually', function () {
    $this->post('/quotation', wfRequest([
        'materials' => [['description' => 'THHN wire 3.5mm', 'quantity' => 12, 'unit' => 'rolls']],
        'materials_confirmed' => true,
    ]))->assertSessionHasNoErrors();

    $quotation = Quotation::with('items')->sole();
    expect($quotation->items->sole())->product_name->toBe('THHN wire 3.5mm')->quantity->toBe(12)
        ->and($quotation->status)->toBe(QuotationStatus::Pending);
});

// --- 3. Uploaded material lists -------------------------------------------------------------

it('accepts the uploaded material-list file on its own when its lines could not be read', function () {
    // OCR is not available on this server: the photo is kept, and its token comes back.
    Process::fake(fn (PendingProcess $p) => Process::result('', 'not found', 1));
    $response = $this->postJson('/quotation/materials/extract', ['file' => wfPng('site-list.png')])
        ->assertStatus(422)
        ->assertJsonPath('reason', 'ocr_unavailable');
    $token = $response->json('token');
    expect($token)->toBeString();

    $this->post('/quotation', wfRequest(['material_token' => $token]))->assertSessionHasNoErrors();

    $quotation = Quotation::sole();
    expect($quotation->material_file_name)->toBe('site-list.png')
        ->and($quotation->notes)->toContain('Only the original file was sent')
        ->and($quotation->items)->toHaveCount(0);
    Storage::disk('local')->assertExists($quotation->material_file_path);
});

// --- 4-5. Product-list files ---------------------------------------------------------------

it('stores several product-list files with the request, once each', function () {
    $token = $this->postJson('/quotation/materials/extract', [
        'file' => UploadedFile::fake()->createWithContent('materials.csv', "Item,Qty\nBreaker,1\n"),
    ])->json('token');

    $this->post('/quotation', wfRequest([
        'materials' => [['description' => 'Breaker', 'quantity' => 1]],
        'materials_confirmed' => true,
        'material_token' => $token,
        'productFiles' => [
            wfPng('photo-1.png', 1),
            wfPng('photo-2.png', 2),
            wfPng('photo-1-again.png', 1), // the same image twice
            UploadedFile::fake()->createWithContent('again.csv', "Item,Qty\nBreaker,1\n"), // same as the material list
            wfPdf('catalog-page.pdf'),
        ],
    ]))->assertSessionHasNoErrors();

    $quotation = Quotation::with('files')->sole();
    expect($quotation->files->pluck('original_name')->all())->toBe(['photo-1.png', 'photo-2.png', 'catalog-page.pdf'])
        ->and($quotation->files->pluck('kind')->unique()->all())->toBe([QuotationFile::KIND_PRODUCT_LIST]);
    foreach ($quotation->files as $file) {
        Storage::disk('local')->assertExists($file->path);
        expect($file->path)->toStartWith('quotations/product-lists/')->not->toContain('photo');
    }
});

it('rejects unsupported, disguised, oversized and too many product-list files', function (array $files, string $errorKey) {
    $this->post('/quotation', wfRequest([
        'materials' => [['description' => 'Breaker', 'quantity' => 1]],
        'materials_confirmed' => true,
        'productFiles' => $files,
    ]))->assertSessionHasErrors($errorKey);

    expect(Quotation::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('quotations/product-lists'))->toBe([]);
})->with([
    'executable' => [[UploadedFile::fake()->createWithContent('setup.exe', 'MZ...')], 'productFiles.0'],
    'script renamed to .pdf' => [[UploadedFile::fake()->createWithContent('list.pdf', '<?php echo 1;')], 'productFiles.0'],
    'over 10 MB' => [[UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')], 'productFiles.0'],
    'more than five files' => [array_map(fn ($i) => wfPng("p{$i}.png", $i), range(1, 6)), 'productFiles'],
]);

it('limits the combined size of product-list files to 20 MB', function () {
    // Three files of 7 MB each are fine one by one, but 21 MB together.
    $files = array_map(fn ($i) => UploadedFile::fake()->create("part{$i}.pdf", 7 * 1024, 'application/pdf'), [1, 2, 3]);

    $this->post('/quotation', wfRequest([
        'materials' => [['description' => 'Breaker', 'quantity' => 1]],
        'materials_confirmed' => true,
        'productFiles' => $files,
    ]))->assertSessionHasErrors(['productFiles' => 'Product-list files can be up to 20 MB together. Please remove a file or send smaller ones.']);

    expect(Quotation::count())->toBe(0);
});

// --- 6. Everything is saved together --------------------------------------------------------

it('saves brands, project details and files together as a request awaiting review', function () {
    $this->post('/quotation', wfRequest([
        'timeline' => '3–6 months',
        'brands' => [['brand' => 'schneider electric', 'details' => '20A breakers']],
        'hasUnlistedBrands' => true,
        'unlistedBrands' => 'Omron',
        'materials' => [['description' => 'Breaker 20A', 'quantity' => 50]],
        'materials_confirmed' => true,
        'productFiles' => [wfPdf()],
    ]))->assertSessionHasNoErrors();

    $quotation = Quotation::with('files', 'items')->sole();
    expect($quotation->status)->toBe(QuotationStatus::Pending)
        ->and($quotation->request_details)->toMatchArray([
            'source' => 'public_form',
            'project_type' => 'Industrial Facility',
            'timeline' => '3-6 months',
            'brands' => [['brand' => 'Schneider Electric', 'details' => '20A breakers']],
            'unlisted_brands' => 'Omron',
        ])
        ->and($quotation->request_details['submitted_at'])->not->toBeEmpty()
        ->and($quotation->files)->toHaveCount(1)
        // Nothing is priced or confirmed from the client's files: custom lines wait for staff.
        ->and((float) $quotation->items->firstWhere('sku', 'CUSTOM')->unit_price)->toBe(0.0);
});

it('only accepts the listed project types and timelines', function () {
    $this->post('/quotation', wfRequest(['projectType' => 'Spaceport', 'materials' => [['description' => 'x', 'quantity' => 1]], 'materials_confirmed' => true]))
        ->assertSessionHasErrors('projectType');
    $this->post('/quotation', wfRequest(['timeline' => 'Someday', 'materials' => [['description' => 'x', 'quantity' => 1]], 'materials_confirmed' => true]))
        ->assertSessionHasErrors('timeline');
});

// --- 7 & 12. Staff review, and who may open the files ---------------------------------------

function wfSubmittedRequest(): Quotation
{
    test()->post('/quotation', wfRequest([
        'materials' => [['description' => 'Breaker', 'quantity' => 2]],
        'materials_confirmed' => true,
        'productFiles' => [wfPdf('wanted-products.pdf')],
    ]))->assertSessionHasNoErrors();

    return Quotation::with('files')->latest('id')->first();
}

it('shows quotation staff the submitted details and files, without storage paths', function () {
    $quotation = wfSubmittedRequest();
    [$staff, $cookies] = wfStaff($this->team);

    $this->actingAs($staff)->withCookies($cookies)
        ->get('/mr-pride/quotations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Quotations/AdminIndex')
            ->where('quotations.data.0.request_details.project_type', 'Industrial Facility')
            ->where('quotations.data.0.files.0.original_name', 'wanted-products.pdf')
            ->has('quotations.data.0.files.0.url')
            ->missing('quotations.data.0.files.0.path')
            ->missing('quotations.data.0.source_file_sha256')
        );

    $this->actingAs($staff)->withCookies($cookies)
        ->get($quotation->files->first()->url)
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('keeps client files away from guests, other modules, other teams and other quotations', function () {
    $quotation = wfSubmittedRequest();
    $file = $quotation->files->first();
    $url = "/mr-pride/quotations/{$quotation->id}/files/{$file->id}";

    $this->get($url)->assertRedirect(); // guest: to the login page

    [$inventoryStaff, $cookies] = wfStaff($this->team, 'staff_inventory');
    $this->actingAs($inventoryStaff)->withCookies($cookies)->get($url)->assertForbidden();

    [$staff, $staffCookies] = wfStaff($this->team);
    $other = Quotation::create([
        'team_id' => $this->team->id, 'quotation_number' => 'Q-OTHER', 'client_name' => 'Other',
        'quotation_date' => now()->toDateString(), 'valid_until' => now()->addDays(30)->toDateString(), 'status' => QuotationStatus::Pending,
    ]);
    $this->actingAs($staff)->withCookies($staffCookies)
        ->get("/mr-pride/quotations/{$other->id}/files/{$file->id}")
        ->assertNotFound();

    $otherTeam = Team::factory()->create(['slug' => 'other-team']);
    [$outsider, $outsiderCookies] = wfStaff($otherTeam);
    $this->actingAs($outsider)->withCookies($outsiderCookies)->get("/other-team/quotations/{$quotation->id}/files/{$file->id}")
        ->assertForbidden();
});

// --- 8-9. Import: creates drafts, never duplicates -----------------------------------------

it('imports a material list as a draft quotation, with catalog prices only for exact matches', function () {
    [$staff, $cookies] = wfStaff($this->team);
    Product::create(['team_id' => $this->team->id, 'sku' => 'BRK-2060', 'name' => 'Breaker 2P 60A', 'category' => 'Breakers', 'price' => 520, 'stock' => 5, 'is_active' => true]);
    $csv = "Item Code,Description,Qty\nBRK-2060,Breaker 2P 60A,3\n,Junction box,10\n";

    $preview = $this->actingAs($staff)->withCookies($cookies)
        ->post('/mr-pride/quotations/import-materials', [
            'file' => UploadedFile::fake()->createWithContent('site-a.csv', $csv),
            'client_name' => 'Metro Builders',
        ], ['Accept' => 'application/json'])
        ->assertOk()->json();
    expect($preview['valid_count'])->toBe(2)
        ->and(Quotation::count())->toBe(0); // the preview saves nothing

    $this->actingAs($staff)->withCookies($cookies)
        ->post('/mr-pride/quotations/import-materials', [
            'file' => UploadedFile::fake()->createWithContent('site-a.csv', $csv),
            'client_name' => 'Metro Builders',
            'execute' => true,
        ])->assertSessionHasNoErrors();

    $quotation = Quotation::with('items')->sole();
    expect($quotation->status)->toBe(QuotationStatus::Draft)
        ->and($quotation->client_name)->toBe('Metro Builders')
        ->and($quotation->material_file_name)->toBe('site-a.csv')
        ->and($quotation->request_details['source'])->toBe('staff_import')
        ->and($quotation->items->map(fn ($i) => [$i->sku, $i->quantity, (float) $i->unit_price])->all())
        ->toBe([['BRK-2060', 3, 520.0], ['CUSTOM', 10, 0.0]]);
});

it('refuses to import the same material list twice', function () {
    [$staff, $cookies] = wfStaff($this->team);
    $csv = "Description,Qty\nJunction box,10\n";
    $import = fn (bool $execute) => $this->actingAs($staff)->withCookies($cookies)->post('/mr-pride/quotations/import-materials', [
        'file' => UploadedFile::fake()->createWithContent('list.csv', $csv),
        'client_name' => 'Metro Builders',
        'execute' => $execute,
    ], $execute ? [] : ['Accept' => 'application/json']);

    $import(true)->assertSessionHasNoErrors();
    $import(false)->assertStatus(409)->assertJsonPath('duplicate_of', Quotation::sole()->quotation_number);
    $import(true)->assertSessionHasErrors('file');

    expect(Quotation::count())->toBe(1);
});

it('skips quotations that already exist when the same CSV is imported again', function () {
    [$admin, $cookies] = wfStaff($this->team, 'admin');
    $csv = "DATE,QUOTE NUMBER,QUANTITY,DESCRIPTION,UNIT PRICE,AMOUNT\n10-25-2026,Q102526-ABC1,5,Conduit,10,50\n";
    $import = fn () => $this->actingAs($admin)->withCookies($cookies)->post('/mr-pride/quotations/import-csv', [
        'file' => UploadedFile::fake()->createWithContent('q.csv', $csv),
        'execute' => true,
        'mode' => 'append',
    ]);

    $import()->assertSessionHas('success', 'Imported 1 quotation(s).');
    $import()->assertSessionHas('success', 'Imported 0 quotation(s). Skipped 1 that already exist: Q102526-ABC1.');

    expect(Quotation::count())->toBe(1)->and(Quotation::sole()->items)->toHaveCount(1);

    $preview = $this->actingAs($admin)->withCookies($cookies)->post('/mr-pride/quotations/import-csv', [
        'file' => UploadedFile::fake()->createWithContent('q.csv', $csv),
    ], ['Accept' => 'application/json'])->json();
    expect($preview['existing_count'])->toBe(1)->and($preview['valid_rows'][0]['exists'])->toBeTrue();
});

it('does not let other modules import quotations', function () {
    [$inventoryStaff, $cookies] = wfStaff($this->team, 'staff_inventory');

    $this->actingAs($inventoryStaff)->withCookies($cookies)->post('/mr-pride/quotations/import-materials', [
        'file' => UploadedFile::fake()->createWithContent('list.csv', "Description,Qty\nBox,1\n"),
        'client_name' => 'X',
        'execute' => true,
    ])->assertForbidden();

    expect(Quotation::count())->toBe(0);
});
