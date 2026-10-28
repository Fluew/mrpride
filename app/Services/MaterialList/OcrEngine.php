<?php

namespace App\Services\MaterialList;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Tesseract OCR for images and scanned PDF pages, and Poppler for PDFs.
 *
 * Tesseract and Poppler's `pdftoppm` (PDF pages to images) and `pdftotext` (word positions in
 * text PDFs) run as separate processes with time limits; their locations are configurable
 * (services.ocr). Images are preprocessed with GD first: grayscale, upscaling small scans,
 * light-on-dark bands (table header bars) flipped to dark-on-light, contrast, light denoise.
 *
 * Text comes back either plain (imageToText, pdfToText) or as TSV with each word's position
 * (imageToTsv, pdfToTsv, pdfTextTsv) for rebuilding tables, see WordTable.
 */
class OcrEngine
{
    /** @var array<string, bool> tool availability, checked once per request */
    private array $probes = [];

    public function tesseractAvailable(): bool
    {
        return $this->probe(config('services.ocr.tesseract', 'tesseract'), ['--version']);
    }

    public function pdfRendererAvailable(): bool
    {
        return $this->probe(config('services.ocr.pdftoppm', 'pdftoppm'), ['-v']);
    }

    public function pdfTextAvailable(): bool
    {
        return $this->probe($this->pdftotextBinary(), ['-v']);
    }

    /** OCR one image file. Throws ExtractionException with a client-safe message on failure. */
    public function imageToText(string $imagePath): string
    {
        return $this->tesseract($imagePath, []);
    }

    /** OCR one image file, returning Tesseract's TSV (one row per word, with its position). */
    public function imageToTsv(string $imagePath): string
    {
        return $this->tesseract($imagePath, ['tsv']);
    }

    /**
     * Render scanned PDF pages to images and OCR them.
     *
     * @return array<int, string> text per page
     */
    public function pdfToText(string $pdfPath, int $pages): array
    {
        return $this->ocrPdf($pdfPath, $pages, fn (string $image) => $this->imageToText($image));
    }

    /** @return array<int, string> Tesseract TSV per scanned page */
    public function pdfToTsv(string $pdfPath, int $pages): array
    {
        return $this->ocrPdf($pdfPath, $pages, fn (string $image) => $this->imageToTsv($image));
    }

    /** Word positions in a PDF's text layer (pdftotext -tsv, all pages). Null when unavailable or failed. */
    public function pdfTextTsv(string $pdfPath): ?string
    {
        if (! $this->pdfTextAvailable()) {
            return null;
        }

        try {
            $result = Process::timeout((int) config('services.ocr.timeout', 60))->run([$this->pdftotextBinary(), '-tsv', '-enc', 'UTF-8', $pdfPath, '-']);
        } catch (\Throwable $e) {
            Log::warning('pdftotext failed: '.$e->getMessage());

            return null;
        }

        return $result->successful() ? $result->output() : null;
    }

    /** @param  array<int, string>  $configs  Tesseract output configs, e.g. ['tsv'] */
    private function tesseract(string $imagePath, array $configs): string
    {
        if (! $this->tesseractAvailable()) {
            throw new ExtractionException('Text recognition (OCR) is not available on the server right now. Please type your material list below instead.', 'ocr_unavailable');
        }

        $work = $this->preprocess($imagePath);
        try {
            $result = Process::timeout((int) config('services.ocr.timeout', 60))->run([
                config('services.ocr.tesseract', 'tesseract'), $work, 'stdout', '--psm', '6', '-l', config('services.ocr.lang', 'eng'), ...$configs,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Tesseract OCR failed: '.$e->getMessage());
            throw new ExtractionException('Text recognition took too long or failed. Please try a clearer image, or type your material list below.', 'ocr_failed');
        } finally {
            if ($work !== $imagePath) {
                @unlink($work);
            }
        }

        if (! $result->successful()) {
            Log::warning('Tesseract OCR exited with an error: '.mb_substr($result->errorOutput(), 0, 300));
            throw new ExtractionException('Text recognition failed for this file. Please try a clearer image, or type your material list below.', 'ocr_failed');
        }

        return $result->output();
    }

    /** @return array<int, string> */
    private function ocrPdf(string $pdfPath, int $pages, callable $ocrImage): array
    {
        if (! $this->pdfRendererAvailable() || ! $this->tesseractAvailable()) {
            throw new ExtractionException('This PDF looks scanned (it contains images, not text), and text recognition is not available on the server right now. Please type your material list below, or upload an Excel/CSV file.', 'ocr_unavailable');
        }

        $dir = storage_path('app/private/tmp/ocr-'.bin2hex(random_bytes(8)));
        File::ensureDirectoryExists($dir);

        try {
            $render = Process::timeout((int) config('services.ocr.timeout', 60) * 2)->run([
                config('services.ocr.pdftoppm', 'pdftoppm'), '-r', '200', '-gray', '-png', '-f', '1', '-l', (string) $pages, $pdfPath, $dir.'/page',
            ]);
            if (! $render->successful()) {
                Log::warning('pdftoppm failed: '.mb_substr($render->errorOutput(), 0, 300));
                throw new ExtractionException('We could not read this scanned PDF. Please upload a clearer copy, or type your material list below.', 'pdf_render_failed');
            }

            $texts = [];
            $images = glob($dir.'/page*.png') ?: [];
            natsort($images);
            foreach ($images as $image) {
                $texts[] = $ocrImage($image);
            }

            return $texts;
        } catch (ExtractionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Scanned PDF OCR failed: '.$e->getMessage());
            throw new ExtractionException('Reading this scanned PDF took too long or failed. Please try a smaller file, or type your material list below.', 'ocr_failed');
        } finally {
            File::deleteDirectory($dir);
        }
    }

    /** Grayscale + upscale small images + flip dark bands + contrast + light denoise. Returns a temp PNG path. */
    private function preprocess(string $path): string
    {
        $info = @getimagesize($path);
        if (! $info || ! function_exists('imagecreatefromstring')) {
            return $path;
        }

        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (! $img) {
            return $path;
        }

        [$w, $h] = [$info[0], $info[1]];
        // Tesseract reads best at roughly 300 DPI; small phone crops are upscaled.
        if ($w < 1600 && $w > 0) {
            $scale = min(3, 1600 / $w);
            $scaled = imagescale($img, (int) ($w * $scale), (int) ($h * $scale), IMG_BICUBIC);
            if ($scaled) {
                imagedestroy($img);
                $img = $scaled;
            }
        }

        imagefilter($img, IMG_FILTER_GRAYSCALE);
        $this->invertDarkBands($img);
        imagefilter($img, IMG_FILTER_CONTRAST, -25);
        imagefilter($img, IMG_FILTER_SMOOTH, 6);

        $out = storage_path('app/private/tmp/ocr-'.bin2hex(random_bytes(8)).'.png');
        File::ensureDirectoryExists(dirname($out));
        imagepng($img, $out);
        imagedestroy($img);

        return $out;
    }

    /**
     * Tesseract misses light text on a dark background, such as a table's header bar. Rows of
     * pixels that are mostly dark (median brightness, so the light letters do not count) are
     * found by sampling, and each such band is inverted.
     */
    private function invertDarkBands(\GdImage $img): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, intdiv($w, 200));

        $dark = [];
        for ($y = 0; $y < $h; $y++) {
            $samples = [];
            for ($x = 0; $x < $w; $x += $step) {
                $samples[] = imagecolorat($img, $x, $y) & 0xFF; // grayscale: any channel is the brightness
            }
            sort($samples);
            $dark[$y] = $samples[intdiv(count($samples), 2)] < 110;
        }

        $start = null;
        for ($y = 0; $y <= $h; $y++) {
            if ($y < $h && $dark[$y]) {
                $start ??= $y;

                continue;
            }
            if ($start !== null && $y - $start >= 8) {
                $band = imagecreatetruecolor($w, $y - $start);
                imagecopy($band, $img, 0, 0, 0, $start, $w, $y - $start);
                imagefilter($band, IMG_FILTER_NEGATE);
                imagecopy($img, $band, 0, $start, 0, 0, $w, $y - $start);
                imagedestroy($band);
            }
            $start = null;
        }
    }

    /** pdftotext sits next to pdftoppm in every Poppler install, so it defaults to that folder. */
    private function pdftotextBinary(): string
    {
        $configured = config('services.ocr.pdftotext');
        if ($configured) {
            return $configured;
        }
        $pdftoppm = (string) config('services.ocr.pdftoppm', 'pdftoppm');

        return preg_replace('/pdftoppm(\.exe)?$/i', 'pdftotext$1', $pdftoppm) ?: 'pdftotext';
    }

    private function probe(string $binary, array $args): bool
    {
        $key = $binary.' '.implode(' ', $args);
        if (array_key_exists($key, $this->probes)) {
            return $this->probes[$key];
        }

        try {
            $this->probes[$key] = Process::timeout(10)->run([$binary, ...$args])->successful();
        } catch (\Throwable) {
            $this->probes[$key] = false;
        }

        return $this->probes[$key];
    }
}
