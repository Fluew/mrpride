<?php

namespace App\Http\Controllers;

use App\Enums\QuotationStatus;
use App\Models\Brand;
use App\Models\Client;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationFile;
use App\Models\Team;
use App\Services\MaterialList\ExtractionException;
use App\Services\MaterialList\FileReader;
use App\Services\MaterialList\MaterialParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public "Request a Quotation" form.
 *
 * A request must include a material list: lines the client confirmed (read from an uploaded file
 * or typed), or at least the uploaded material-list file itself, for staff to read. Brands, extra
 * product-list files and specification documents are optional. Everything is saved with the
 * quotation as client-submitted information with status Pending: nothing is approved, priced or
 * confirmed as available until staff review it.
 */
class PublicQuotationController extends Controller
{
    public const PROJECT_TYPES = ['Commercial Building', 'Industrial Facility', 'Infrastructure/Public Works', 'Residential Complex', 'Other'];

    public const TIMELINES = ['Immediate (ASAP)', '1-3 months', '3-6 months', 'Over 6 months'];

    public const MAX_PRODUCT_FILES = 5;

    /** Together, all product-list files may not exceed this (each file is also limited to 10 MB). */
    public const MAX_PRODUCT_FILES_BYTES = 20 * 1024 * 1024;

    public const PRODUCT_FILE_EXTENSIONS = ['pdf', 'xls', 'xlsx', 'csv', 'jpg', 'jpeg', 'png'];

    public const PRODUCT_FILES_DIR = 'quotations/product-lists';

    /**
     * Distinct, non-empty product brands from active catalog products and partner brands
     * (case-insensitive, first spelling wins), sorted alphabetically.
     *
     * @return list<string>
     */
    public static function availableBrands(): array
    {
        $partnerBrands = Schema::hasTable('brands')
            ? Brand::query()->where('is_active', true)->pluck('name')
            : collect();

        $productBrands = Product::query()
            ->where('is_active', true)
            ->whereNotNull('brand')
            ->pluck('brand');

        return $partnerBrands->concat($productBrands)
            ->map(fn ($b) => trim((string) $b))
            ->filter(fn ($b) => $b !== '')
            ->unique(fn ($b) => mb_strtolower($b))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Show the public quotation request form with the brands available in the catalog.
     */
    public function create(): Response
    {
        return Inertia::render('Quotations/Create', [
            'brands' => self::availableBrands(),
        ]);
    }

    /**
     * Store a public quotation request.
     */
    public function store(Request $request, FileReader $reader): RedirectResponse
    {
        $validated = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'contactName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'projectType' => ['nullable', 'string', Rule::in(self::PROJECT_TYPES)],
            // The form shows "1–3 months"; both dash styles are accepted.
            'timeline' => ['nullable', 'string', Rule::in([...self::TIMELINES, '1–3 months', '3–6 months'])],
            'brands' => ['nullable', 'array', 'max:50'],
            'hasUnlistedBrands' => ['nullable', 'boolean'],
            'unlistedBrands' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,zip', 'max:10240'],

            // Material list (extracted from an uploaded file, or typed) the client reviewed and confirmed.
            // Prices and totals are never taken from the client; they come from the catalog below.
            'materials' => ['nullable', 'array', 'max:'.MaterialParser::MAX_ITEMS],
            'materials.*.description' => ['required', 'string', 'max:500'],
            'materials.*.quantity' => ['required', 'integer', 'min:1', 'max:'.MaterialParser::MAX_QUANTITY],
            'materials.*.unit' => ['nullable', 'string', 'max:30'],
            'materials.*.brand' => ['nullable', 'string', 'max:100'],
            'materials.*.specs' => ['nullable', 'string', 'max:500'],
            'materials.*.remarks' => ['nullable', 'string', 'max:500'],
            'materials.*.product_id' => ['nullable', 'integer'],
            'material_token' => ['nullable', 'string', 'max:64'],
            'materials_confirmed' => ['nullable', 'boolean'],

            // Optional files listing specific products the client wants quoted.
            'productFiles' => ['nullable', 'array', 'max:'.self::MAX_PRODUCT_FILES],
            'productFiles.*' => ['file', 'max:10240', 'extensions:'.implode(',', self::PRODUCT_FILE_EXTENSIONS)],
        ], [
            'projectType.in' => 'Please choose a project type from the list.',
            'timeline.in' => 'Please choose an expected timeline from the list.',
            'materials.*.description.required' => 'Every material line needs a description.',
            'materials.*.quantity.required' => 'Every material line needs a quantity.',
            'materials.*.quantity.integer' => 'Quantities must be whole numbers.',
            'materials.*.quantity.min' => 'Quantities must be at least 1.',
            'productFiles.max' => 'You can attach up to '.self::MAX_PRODUCT_FILES.' product-list files.',
            'productFiles.*.max' => 'Each product-list file must be 10 MB or smaller.',
            'productFiles.*.extensions' => 'Product lists must be PDF, Excel (.xls/.xlsx), CSV, JPG or PNG files.',
            'productFiles.*.uploaded' => 'A product-list file could not be uploaded. It may be larger than 10 MB.',
        ]);

        $materials = array_values($validated['materials'] ?? []);
        $materialUpload = $this->materialUpload($request, $validated['material_token'] ?? null);

        // The material list is required: confirmed lines, or at least the uploaded file.
        if ($materials === [] && $materialUpload === null) {
            return back()->withErrors([
                'materials' => 'Please add your material list: upload a file, or enter the items and quantities manually.',
            ])->withInput();
        }
        if ($materials !== [] && ! $request->boolean('materials_confirmed')) {
            return back()->withErrors([
                'materials_confirmed' => 'Please confirm that the material list matches what you need.',
            ])->withInput();
        }

        $productFiles = $this->checkProductFiles($request, $reader);

        [$brandItems, $catalog] = $this->brandItems($request);
        $brands = collect($brandItems)->map(fn ($i) => $catalog[mb_strtolower($i['brand'])])->unique()->values()->all();

        $unlisted = $request->boolean('hasUnlistedBrands') ? trim((string) ($validated['unlistedBrands'] ?? '')) : '';
        if ($request->boolean('hasUnlistedBrands') && $unlisted === '') {
            return back()->withErrors([
                'unlistedBrands' => 'Please enter the brand names you are looking for.',
            ])->withInput();
        }

        $team = Team::first();
        if ($team) {
            DB::transaction(function () use ($request, $validated, $team, $brandItems, $brands, $catalog, $unlisted, $materials, $materialUpload, $productFiles) {
                $quotation = $this->createQuotation($request, $validated, $team, $brandItems, $brands, $catalog, $unlisted);

                if ($materials !== []) {
                    $this->addMaterials($quotation, $team, $materials);
                }
                $materialHash = $materialUpload ? $this->keepMaterialFile($request, $quotation, $materialUpload, $materials !== []) : null;
                $this->keepProductFiles($quotation, $productFiles, $materialHash);
            });
        }

        return back()->with('success', 'Thank you! Your quotation request has been received. Our team will review your material list and get in touch shortly.');
    }

    /**
     * The material-list file this visitor uploaded in this session, when the form refers to it.
     *
     * @return array{token: string, path: string, name: string}|null
     */
    private function materialUpload(Request $request, ?string $token): ?array
    {
        $upload = $request->session()->get(MaterialListController::SESSION_KEY);

        return $token && $upload && hash_equals($upload['token'], $token) && Storage::disk('local')->exists($upload['path'])
            ? $upload
            : null;
    }

    /**
     * Check product-list files by content (not just the name) and their combined size.
     *
     * @return array<int, UploadedFile>
     */
    private function checkProductFiles(Request $request, FileReader $reader): array
    {
        $files = array_values(array_filter((array) $request->file('productFiles', []), fn ($f) => $f instanceof UploadedFile));

        $total = array_sum(array_map(fn (UploadedFile $f) => (int) $f->getSize(), $files));
        if ($total > self::MAX_PRODUCT_FILES_BYTES) {
            throw ValidationException::withMessages([
                'productFiles' => 'Product-list files can be up to 20 MB together. Please remove a file or send smaller ones.',
            ]);
        }

        foreach ($files as $i => $file) {
            try {
                $reader->detectType($file->getRealPath() ?: $file->getPathname(), $file->getClientOriginalName());
            } catch (ExtractionException $e) {
                throw ValidationException::withMessages([
                    "productFiles.{$i}" => Str::limit($file->getClientOriginalName(), 60).': '.$e->getMessage(),
                ]);
            }
        }

        return $files;
    }

    /**
     * Brands chosen in the form, limited to the catalog (case-insensitive, catalog spelling).
     * Accepts ['Schneider'] and [['brand' => 'Schneider', 'details' => '...']].
     *
     * @return array{0: array<int, array{brand: string, details: string}>, 1: Collection}
     */
    private function brandItems(Request $request): array
    {
        $brandItems = [];
        foreach ((array) $request->input('brands', []) as $item) {
            $name = trim((string) (is_array($item) ? ($item['brand'] ?? $item['name'] ?? '') : $item));
            $details = is_array($item) ? trim((string) ($item['details'] ?? $item['quantity'] ?? $item['specifics'] ?? '')) : '';
            if ($name !== '') {
                $brandItems[] = ['brand' => $name, 'details' => Str::limit($details, 1000, '')];
            }
        }

        $catalog = collect(self::availableBrands())->keyBy(fn ($b) => mb_strtolower($b));
        $unknown = collect($brandItems)->pluck('brand')->map(fn ($b) => mb_strtolower($b))->reject(fn ($b) => $catalog->has($b));
        if ($unknown->isNotEmpty()) {
            throw ValidationException::withMessages([
                'brands' => 'One or more selected brands are not available. Please choose from the list.',
            ]);
        }

        return [$brandItems, $catalog];
    }

    private function createQuotation(Request $request, array $validated, Team $team, array $brandItems, array $brands, $catalog, string $unlisted): Quotation
    {
        $client = Client::firstOrCreate(
            ['team_id' => $team->id, 'company_name' => $validated['company']],
            ['contact_person' => $validated['contactName'], 'email' => $validated['email'], 'phone' => $validated['phone']]
        );

        $projectType = $validated['projectType'] ?? 'General';
        $timeline = str_replace('–', '-', $validated['timeline'] ?? 'Flexible');

        $notes = "Project Type: {$projectType}\nExpected Timeline: {$timeline}\n";
        if ($brands !== []) {
            $notes .= "\n--- Brands Requested ---\n".implode(', ', $brands)."\n";
            $withDetails = array_filter($brandItems, fn ($i) => $i['details'] !== '');
            if ($withDetails !== []) {
                $notes .= "\n--- Specific Requirements & Quantities ---\n";
                foreach ($withDetails as $item) {
                    $notes .= '• '.$catalog[mb_strtolower($item['brand'])].": {$item['details']}\n";
                }
            }
        }
        if ($unlisted !== '') {
            $notes .= "\n--- Brands Not Listed ---\n".$unlisted."\n";
        }

        $quotation = Quotation::create([
            'team_id' => $team->id,
            'quotation_number' => Quotation::generateNumber($team->id),
            'client_id' => $client->id,
            'client_name' => $validated['company'],
            'contact_person' => $validated['contactName'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 0.00,
            'discount' => 0.00,
            'tax_rate' => 12.00,
            'tax_amount' => 0.00,
            'grand_total' => 0.00,
            'status' => QuotationStatus::Pending,
            'attachment_path' => $request->hasFile('attachment') ? $request->file('attachment')->store('quotations/attachments', 'local') : null,
            'notes' => $notes,
            // The same request in a structured form, for the staff review screen.
            'request_details' => [
                'source' => 'public_form',
                'project_type' => $projectType,
                'timeline' => $timeline,
                'brands' => array_values(array_map(fn ($i) => [
                    'brand' => $catalog[mb_strtolower($i['brand'])],
                    'details' => $i['details'],
                ], $brandItems)),
                'unlisted_brands' => $unlisted !== '' ? $unlisted : null,
                'submitted_at' => now()->toIso8601String(),
            ],
        ]);

        // One line per selected brand item (details appended), for the existing item views.
        $seen = [];
        foreach ($brandItems as $item) {
            $canonical = $catalog[mb_strtolower($item['brand'])];
            $key = $canonical.'|'.$item['details'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $quotation->items()->create([
                'product_id' => null,
                'sku' => 'BRAND',
                'product_name' => 'Brand: '.$canonical.($item['details'] !== '' ? ' - '.Str::limit($item['details'], 100) : ''),
                'quantity' => 1,
                'unit_price' => 0.00,
                'discount' => 0.00,
                'subtotal' => 0.00,
            ]);
        }

        if ($unlisted !== '') {
            $quotation->items()->create([
                'product_id' => null,
                'sku' => 'BRAND-UNLISTED',
                'product_name' => 'Brands not listed: '.Str::limit($unlisted, 75),
                'quantity' => 1,
                'unit_price' => 0.00,
                'discount' => 0.00,
                'subtotal' => 0.00,
            ]);
        }

        return $quotation;
    }

    /**
     * Add the confirmed material lines as quotation items.
     *
     * A line linked to an active catalog product (chosen by the client from our suggestions) gets
     * that product's current selling price as an estimate. Every other line is a custom item at
     * ₱0.00 for staff to price. Prices and totals are calculated here, never taken from the client.
     *
     * @param  array<int, array<string, mixed>>  $materials
     */
    private function addMaterials(Quotation $quotation, Team $team, array $materials): void
    {
        $productIds = collect($materials)->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique();
        $products = Product::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->whereIn('id', $productIds)
            ->get(['id', 'sku', 'name', 'price'])
            ->keyBy('id');

        $subtotal = 0.0;
        $pending = 0;
        $lines = [];

        foreach ($materials as $i => $m) {
            $description = trim((string) $m['description']);
            $qty = (int) $m['quantity'];
            $brand = trim((string) ($m['brand'] ?? ''));
            $specs = trim((string) ($m['specs'] ?? ''));
            $unit = trim((string) ($m['unit'] ?? ''));
            $remarks = trim((string) ($m['remarks'] ?? ''));
            $product = isset($m['product_id']) ? $products->get((int) $m['product_id']) : null;
            $price = $product && (float) $product->price > 0 ? round((float) $product->price, 2) : 0.0;
            $lineTotal = round($price * $qty, 2);

            $name = $product
                ? $product->name
                : $description.($brand !== '' ? " — {$brand}" : '').($specs !== '' ? " ({$specs})" : '');

            $quotation->items()->create([
                'product_id' => $product?->id,
                'sku' => $product?->sku ?? 'CUSTOM',
                'product_name' => Str::limit($name, 250),
                'quantity' => $qty,
                'unit_price' => $price,
                'discount' => 0.00,
                'subtotal' => $lineTotal,
            ]);

            $subtotal += $lineTotal;
            if ($price <= 0) {
                $pending++;
            }

            $lines[] = ($i + 1).'. '.$description
                .($brand !== '' ? " | Brand: {$brand}" : '')
                .($specs !== '' ? " | Specs: {$specs}" : '')
                ." | Qty: {$qty}".($unit !== '' ? " {$unit}" : '')
                .($remarks !== '' ? " | Remarks: {$remarks}" : '')
                .($product ? " | Matched: {$product->sku}" : ' | Custom item (for staff pricing)');
        }

        // Same formula as staff quotations (QuotationController): tax on the discounted subtotal.
        $taxRate = (float) ($quotation->tax_rate ?? config('quotation.tax_rate', 12));
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        $notes = rtrim((string) $quotation->notes)."\n\n--- Material List (".count($materials).' item'.(count($materials) === 1 ? '' : 's').') ---'."\n"
            .implode("\n", $lines)."\n";
        $notes .= $pending > 0
            ? "\n{$pending} item(s) need pricing by staff. Totals shown are estimates from catalog prices only.\n"
            : "\nTotals are estimates from catalog prices; staff confirm official pricing.\n";

        $quotation->update([
            'subtotal' => round($subtotal, 2),
            'tax_amount' => $taxAmount,
            'grand_total' => round($subtotal + $taxAmount, 2),
            'notes' => $notes,
        ]);
    }

    /**
     * Keep the client's original material-list file with the request. Returns its fingerprint.
     *
     * @param  array{token: string, path: string, name: string}  $upload
     * @param  bool  $hasLines  whether material lines were sent too
     */
    private function keepMaterialFile(Request $request, Quotation $quotation, array $upload, bool $hasLines): ?string
    {
        if (! Schema::hasColumn('quotations', 'material_file_path')) {
            return null;
        }

        $disk = Storage::disk('local');
        $hash = hash_file('sha256', $disk->path($upload['path'])) ?: null;
        $target = 'quotations/attachments/material-list-'.basename($upload['path']);
        $disk->move($upload['path'], $target);
        $request->session()->forget(MaterialListController::SESSION_KEY);

        $quotation->update([
            'material_file_path' => $target,
            'material_file_name' => $upload['name'],
            'source_file_sha256' => $hash,
        ] + ($hasLines ? [] : [
            // Nothing could be read from the file (or the client chose not to): staff read it themselves.
            'notes' => rtrim((string) $quotation->notes).'

--- Material List ---
Only the original file was sent ('.$upload['name'].'). Please read the items and quantities from it.
',
        ]));

        return $hash;
    }

    /**
     * Store the product-list files. A file identical to the material list, or to another
     * product file, is not stored twice.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function keepProductFiles(Quotation $quotation, array $files, ?string $materialHash): void
    {
        $seen = array_filter([$materialHash]);
        foreach ($files as $file) {
            $path = $file->getRealPath() ?: $file->getPathname();
            $hash = hash_file('sha256', $path);
            if (in_array($hash, $seen, true)) {
                continue;
            }
            $seen[] = $hash;

            $quotation->files()->create([
                'kind' => QuotationFile::KIND_PRODUCT_LIST,
                'path' => $file->storeAs(self::PRODUCT_FILES_DIR, Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local'),
                'original_name' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, ''),
                'mime' => $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'sha256' => $hash,
            ]);
        }

        $count = $quotation->files()->count();
        if ($count > 0) {
            $quotation->update(['notes' => rtrim((string) $quotation->notes)."\n\n--- Product list files ---\n{$count} file(s) attached for staff review.\n"]);
        }
    }
}
