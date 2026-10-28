<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\MaterialList\ExtractionException;
use App\Services\MaterialList\FileReader;
use App\Services\MaterialList\HeaderMapper;
use App\Services\MaterialList\MaterialParser;
use App\Services\MaterialList\OcrEngine;
use App\Services\MaterialList\ProductMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public quotation form: upload a material list (PDF, Excel, CSV, JPG, PNG) and get back the
 * extracted lines for review. Nothing is saved as a quotation here; that happens on submit
 * (PublicQuotationController@store) using only the lines the client confirmed.
 *
 * The file is kept privately under a random name. The browser only receives an opaque token tied
 * to its own session, so it can never point the server at another path or another visitor's file.
 */
class MaterialListController extends Controller
{
    public const DIR = 'quotations/material-lists';

    public const SESSION_KEY = 'material_list_upload';

    public function extract(Request $request, FileReader $reader): JsonResponse
    {
        $invalid = $this->validationError($request, [
            'file' => ['required', 'file', 'max:10240', 'extensions:'.implode(',', FileReader::EXTENSIONS)],
        ], [
            'file.required' => 'Please choose a file to upload.',
            'file.max' => 'The file is larger than 10 MB. Please upload a smaller file.',
            'file.extensions' => 'This file type is not supported. Please upload a PDF, Excel (.xlsx/.xls), CSV, JPG or PNG file.',
            'file.uploaded' => 'The file could not be uploaded. It may be larger than 10 MB.',
        ]);
        if ($invalid) {
            return $invalid;
        }

        $this->pruneOldUploads();
        $this->forgetUpload($request); // replacing a previous file

        $file = $request->file('file');
        $originalName = mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 200);
        $ext = strtolower($file->getClientOriginalExtension());
        $stored = $file->storeAs(self::DIR, Str::uuid().'.'.$ext, 'local');
        $path = Storage::disk('local')->path($stored);

        try {
            $type = $reader->detectType($path, $originalName);
        } catch (ExtractionException $e) {
            Storage::disk('local')->delete($stored);

            return $this->failure($e);
        }

        $token = Str::random(40);
        $request->session()->put(self::SESSION_KEY, [
            'token' => $token, 'path' => $stored, 'name' => $originalName, 'type' => $type, 'size' => $file->getSize(), 'at' => now()->timestamp,
        ]);

        return $this->process($request, $reader, null, null);
    }

    /** Re-read the stored table with the columns the client chose. */
    public function remap(Request $request, FileReader $reader): JsonResponse
    {
        $rules = [
            'token' => ['required', 'string'],
            'mapping' => ['required', 'array'],
            'mapping.description' => ['required', 'integer', 'min:0', 'max:'.(FileReader::MAX_COLS - 1)],
            'mapping.quantity' => ['required', 'integer', 'min:0', 'max:'.(FileReader::MAX_COLS - 1)],
            'mapping.*' => ['nullable', 'integer', 'min:0', 'max:'.(FileReader::MAX_COLS - 1)],
            'header_index' => ['nullable', 'integer', 'min:0', 'max:'.FileReader::MAX_ROWS],
        ];
        $invalid = $this->validationError($request, $rules, [
            'mapping.description.required' => 'Please choose the column that holds the item description.',
            'mapping.quantity.required' => 'Please choose the column that holds the quantity.',
        ]);
        if ($invalid) {
            return $invalid;
        }
        $validated = $request->only(['token', 'mapping', 'header_index']);

        $mapping = array_filter(
            array_intersect_key($validated['mapping'], HeaderMapper::SYNONYMS),
            fn ($v) => $v !== null
        );

        return $this->process($request, $reader, array_map('intval', $mapping), $validated['header_index'] ?? null, $validated['token']);
    }

    private function process(Request $request, FileReader $reader, ?array $mapping, ?int $headerIndex, ?string $token = null): JsonResponse
    {
        $upload = $request->session()->get(self::SESSION_KEY);
        if (! $upload || ($token !== null && ! hash_equals($upload['token'], $token)) || ! Storage::disk('local')->exists($upload['path'])) {
            return response()->json(['message' => 'This upload has expired. Please choose the file again.', 'reason' => 'expired'], 410);
        }

        @set_time_limit(180); // OCR of a 20-page scan can take a while

        try {
            $read = $reader->read(Storage::disk('local')->path($upload['path']), $upload['type']);

            if (isset($read['rows'])) {
                if ($read['rows'] === []) {
                    throw new ExtractionException('No rows were found in this file.', 'empty');
                }
                $parsed = MaterialParser::parseTable($read['rows'], $mapping, $headerIndex, $read['method']);
                $rawText = null;
            } else {
                $lines = MaterialParser::cleanLines($read['pages']);
                if ($lines === []) {
                    throw new ExtractionException(
                        $read['method'] === 'ocr' || $read['method'] === 'pdf-ocr'
                            ? 'No readable text was found. The image may be blurry or too dark. Please try a clearer photo, or type your material list below.'
                            : 'No text was found in this PDF. Please upload a different file, or type your material list below.',
                        'no_text'
                    );
                }
                $parsed = MaterialParser::parseText($lines, $read['method']);
                $rawText = mb_substr(implode("\n", $lines), 0, 20000);
            }
        } catch (ExtractionException $e) {
            return $this->failure($e, keepUpload: true, token: $upload['token']);
        } catch (\Throwable $e) {
            Log::error('Material list extraction failed: '.$e->getMessage(), ['type' => $upload['type']]);

            return $this->failure(new ExtractionException('Something went wrong while reading this file. Please try again, or type your material list below.', 'server_error'), keepUpload: true, token: $upload['token']);
        }

        $parsed['items'] = (new ProductMatcher(Team::first()?->id))->matchAll($parsed['items']);
        $parsed['unparsed_lines'] = array_slice($parsed['unparsed_lines'], 0, 200);

        $message = match (true) {
            $parsed['items'] !== [] => null,
            ! $parsed['confident'] && isset($read['rows']) => null, // the client maps the columns next
            default => 'No materials were detected in this file. Please check the file, map the columns, or type your list below.',
        };

        return response()->json([
            'token' => $upload['token'],
            'file' => ['name' => $upload['name'], 'size' => $upload['size'], 'type' => $upload['type']],
            'method' => $read['method'],
            'sheet' => $read['sheet'] ?? null,
            'sheets' => $read['sheets'] ?? [],
            'structured' => isset($read['rows']),
            'ocr_available' => app(OcrEngine::class)->tesseractAvailable(),
            'show_prices' => (bool) config('quotation.show_estimated_prices', true),
            'tax_rate' => (float) config('quotation.tax_rate', 12),
            'raw_text' => $rawText,
            'message' => $message,
        ] + $parsed);
    }

    /**
     * This app renders validation errors as redirects for non-/api URLs (bootstrap/app.php), so
     * these JSON endpoints return their own 422 with the usual {message, errors} shape.
     */
    private function validationError(Request $request, array $rules, array $messages): ?JsonResponse
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if (! $validator->fails()) {
            return null;
        }

        return response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
            'reason' => 'invalid',
        ], 422);
    }

    /**
     * When the file is kept (it is valid, only reading it failed), its token comes back too: the
     * client can still send the file itself as the material list, for staff to read.
     */
    private function failure(ExtractionException $e, bool $keepUpload = false, ?string $token = null): JsonResponse
    {
        return response()->json(['message' => $e->getMessage(), 'reason' => $e->reason, 'can_retry' => $keepUpload]
            + ($keepUpload && $token ? ['token' => $token] : []), 422);
    }

    private function forgetUpload(Request $request): void
    {
        if ($old = $request->session()->pull(self::SESSION_KEY)) {
            Storage::disk('local')->delete($old['path']);
        }
    }

    /** Uploads not turned into a quotation within a day are deleted. */
    private function pruneOldUploads(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::DIR) as $file) {
            if ($disk->lastModified($file) < now()->subDay()->timestamp) {
                $disk->delete($file);
            }
        }
    }
}
