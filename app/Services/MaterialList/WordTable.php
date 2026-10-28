<?php

namespace App\Services\MaterialList;

/**
 * Rebuilds a table from word positions: Tesseract's or pdftotext's "-tsv" output.
 *
 * Words are grouped into lines. The first line that $isHeader accepts names the columns. Where
 * one column ends and the next begins is read from the data below it: between two headers, the
 * edge goes in the blank strip (the "river") that the rows leave between the columns. Header
 * words with no river between them belong to one column ("Product Name"). Each word then goes
 * into the column it sits in. This keeps columns apart even when the text has no separators,
 * as in scans, photos and most PDFs.
 */
final class WordTable
{
    /**
     * @param  array<int, string>  $tsvPages  TSV output (one string may hold several pages)
     * @param  callable(array<int, string>): bool  $isHeader  is this line the column header row?
     * @param  bool  $tesseractLines  group words by Tesseract's own line ids (copes with slightly
     *                                tilted photos); otherwise by vertical position (PDF text)
     * @return array{aligned: bool, rows: array<int, array<int, string>>} aligned = a header row was found
     */
    public static function build(array $tsvPages, callable $isHeader, bool $tesseractLines): array
    {
        $columns = null; // [{text, x0, x1}], edges between them
        $edges = [];
        $rows = [];

        foreach ($tsvPages as $tsv) {
            foreach (self::pages($tsv) as $words) {
                $lines = self::lines($words, $tesseractLines);

                $headerAt = null;
                foreach ($lines as $i => $line) {
                    $phrases = array_column(self::phrases($line), 'text');
                    if ($isHeader($phrases) || $isHeader(array_column($line, 'text'))) {
                        $headerAt = $i;
                        break;
                    }
                }

                $data = $headerAt === null ? $lines : array_slice($lines, $headerAt + 1);
                if ($headerAt !== null) {
                    [$found, $edges] = self::columns($lines[$headerAt], $data);
                    if ($columns === null) {
                        foreach (array_slice($lines, 0, $headerAt) as $title) {
                            $rows[] = array_column(self::phrases($title), 'text');
                        }
                        $rows[] = array_column($found, 'text');
                        $first = ['row' => array_key_last($rows), 'columns' => $found, 'letter' => self::letterHeight($lines[$headerAt])];
                    }
                    $columns = $found; // a repeated header on a later page realigns the columns
                } elseif ($columns === null) {
                    foreach ($lines as $line) {
                        $rows[] = array_column(self::phrases($line), 'text'); // no table (yet): plain lines
                    }

                    continue;
                }

                self::addRows($rows, $data, $columns, $edges);
            }
        }

        if (isset($first)) {
            self::joinEmptyColumns($rows, $first['row'], $first['columns'], $first['letter']);
        }

        return ['aligned' => $columns !== null, 'rows' => $rows];
    }

    /** Place each data line into the columns; skip notes and page numbers, join wrapped cells. */
    private static function addRows(array &$rows, array $lines, array $columns, array $edges): void
    {
        $lastData = null;
        $prevBottom = null;
        foreach ($lines as $line) {
            // Drop "Page 2 of 3" wherever it sits on the line.
            $line = array_merge(...array_map(
                fn ($p) => self::isPageMarker($p['text']) ? [] : $p['words'],
                self::phrases($line) ?: [['text' => '', 'words' => []]],
            ));
            if ($line === []) {
                continue;
            }
            [$top, $bottom, $height] = self::extent($line);

            $cells = self::place($line, count($columns), $edges);
            if ($cells === null) {
                continue; // a sentence running across the columns: a note or footer, not a row
            }

            $filled = array_keys(array_filter($cells, fn ($c) => $c !== ''));
            $close = $prevBottom !== null && $top - $prevBottom < 0.8 * $height;
            if ($lastData !== null && $close && count($filled) === 1 && $filled[0] > 0) {
                // A long cell wrapped onto the next line.
                $rows[$lastData][$filled[0]] = trim($rows[$lastData][$filled[0]].' '.$cells[$filled[0]]);
            } else {
                $rows[] = $cells;
                $lastData = array_key_last($rows);
            }
            $prevBottom = $bottom;
        }
    }

    /**
     * Columns from the header words, with edges read from the rows below.
     *
     * @return array{0: array<int, array{text: string, x0: float, x1: float}>, 1: array<int, float>}
     */
    private static function columns(array $header, array $data): array
    {
        $coverage = self::coverage($data);
        $rows = count($data);
        $letter = self::letterHeight($header);

        $columns = [];
        foreach ($header as $w) {
            $last = array_key_last($columns);
            if ($last !== null) {
                // Same column when the gap is a normal space between words, or when the rows fill
                // it (at least 2, so one footer sentence running across cannot join two columns).
                $same = 0.5 * $letter > $w['x0'] - $columns[$last]['x1']
                    || ($rows >= 2 && $coverage->min($columns[$last]['x1'], $w['x0']) >= max(2, 0.3 * $rows));
                if ($same) {
                    $columns[$last]['text'] .= ' '.$w['text'];
                    $columns[$last]['x1'] = max($columns[$last]['x1'], $w['x1']);

                    continue;
                }
            }
            $columns[] = ['text' => $w['text'], 'x0' => $w['x0'], 'x1' => $w['x1']];
        }

        $edges = [];
        for ($i = 0; $i < count($columns) - 1; $i++) {
            $a = $columns[$i];
            $b = $columns[$i + 1];
            if ($rows === 0) {
                $edges[] = ($a['x1'] + $b['x0']) / 2;

                continue;
            }
            // The river is normally in the space between the two headers. Numbers wider than their
            // header (right-aligned) can reach into it, so look between the header middles too.
            $between = [$a['x1'], $b['x0']];
            $middles = [($a['x0'] + $a['x1']) / 2, ($b['x0'] + $b['x1']) / 2];
            $range = $coverage->min(...$between) <= $coverage->min(...$middles) ? $between : $middles;
            $edges[] = $coverage->river(...$range);
        }

        return [$columns, $edges];
    }

    /**
     * A header split in two by the rule above ("Product" | "Name" over short names) leaves one
     * part with no data. Such an empty column is joined to a neighbour whose header is only a
     * space away.
     */
    private static function joinEmptyColumns(array &$rows, int $headerRow, array $columns, float $letter): void
    {
        for ($j = count($columns) - 1; $j >= 0; $j--) {
            $empty = true;
            for ($r = $headerRow + 1; $r < count($rows) && $empty; $r++) {
                $empty = ($rows[$r][$j] ?? '') === '';
            }
            if (! $empty) {
                continue;
            }

            $gapLeft = $j > 0 ? $columns[$j]['x0'] - $columns[$j - 1]['x1'] : INF;
            $gapRight = $j < count($columns) - 1 ? $columns[$j + 1]['x0'] - $columns[$j]['x1'] : INF;
            $into = $gapLeft <= $gapRight ? $j - 1 : $j + 1;
            if (min($gapLeft, $gapRight) >= $letter) {
                continue;
            }

            $rows[$headerRow][$into] = $into < $j
                ? $rows[$headerRow][$into].' '.$rows[$headerRow][$j]
                : $rows[$headerRow][$j].' '.$rows[$headerRow][$into];
            for ($r = $headerRow; $r < count($rows); $r++) {
                array_splice($rows[$r], $j, 1);
            }
            $columns[$into]['x0'] = min($columns[$into]['x0'], $columns[$j]['x0']);
            $columns[$into]['x1'] = max($columns[$into]['x1'], $columns[$j]['x1']);
            array_splice($columns, $j, 1);
        }
    }

    /**
     * Height of plain letters in a line. OCR boxes vary a lot (some include the whole header
     * bar), so the lower quartile is used rather than the median.
     */
    private static function letterHeight(array $line): float
    {
        $heights = array_map(fn ($w) => $w['y1'] - $w['y0'], $line);
        sort($heights);

        return (float) ($heights[intdiv(count($heights), 4)] ?? 10);
    }

    /**
     * Put each word in the column it sits in. Null when the line is a sentence running across
     * columns (3+ words with normal spacing over 2+ columns), which is a note or footer, not a row.
     */
    private static function place(array $line, int $count, array $edges): ?array
    {
        $column = function (array $w) use ($edges): int {
            $x = ($w['x0'] + $w['x1']) / 2;
            $i = 0;
            while ($i < count($edges) && $x > $edges[$i]) {
                $i++;
            }

            return $i;
        };

        foreach (self::phrases($line) as $p) {
            if (count($p['words']) >= 3 && count(array_unique(array_map($column, $p['words']))) > 1) {
                return null;
            }
        }

        $cells = array_fill(0, $count, '');
        foreach ($line as $w) {
            $c = $column($w);
            $cells[$c] = trim($cells[$c].' '.$w['text']);
        }

        return $cells;
    }

    /** @return array<int, array<int, array{text: string, x0: float, x1: float, y0: float, y1: float, line: string}>> words per page */
    private static function pages(string $tsv): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($tsv)) ?: [];
        $head = array_flip(explode("\t", (string) array_shift($lines)));
        foreach (['level', 'page_num', 'left', 'top', 'width', 'height', 'text'] as $required) {
            if (! isset($head[$required])) {
                return [];
            }
        }

        $pages = [];
        foreach ($lines as $raw) {
            $f = explode("\t", $raw);
            $text = trim($f[$head['text']] ?? '');
            if (($f[$head['level']] ?? '') !== '5' || $text === '' || str_starts_with($text, '###')) {
                continue;
            }
            // Tesseract marks specks and stray lines with very low confidence.
            if (isset($head['conf']) && (float) ($f[$head['conf']] ?? 100) < 15 && ! preg_match('/[\p{L}\p{N}]{2}/u', $text)) {
                continue;
            }
            $x = (float) $f[$head['left']];
            $y = (float) $f[$head['top']];
            $line = implode('-', array_map(fn ($k) => isset($head[$k]) ? ($f[$head[$k]] ?? '') : '', ['block_num', 'par_num', 'line_num']));
            $pages[(int) $f[$head['page_num']]][] = [
                'text' => $text, 'x0' => $x, 'x1' => $x + (float) $f[$head['width']],
                'y0' => $y, 'y1' => $y + (float) $f[$head['height']], 'line' => $line,
            ];
        }
        ksort($pages);

        return array_values($pages);
    }

    /** Words grouped into lines, top to bottom, each line left to right. */
    private static function lines(array $words, bool $tesseractLines): array
    {
        $lines = [];
        if ($tesseractLines) {
            foreach ($words as $w) {
                $lines[$w['line']][] = $w;
            }
            $lines = array_values($lines);
        } else {
            usort($words, fn ($a, $b) => ($a['y0'] + $a['y1']) <=> ($b['y0'] + $b['y1']));
            $current = null;
            foreach ($words as $w) {
                $mid = ($w['y0'] + $w['y1']) / 2;
                $h = $w['y1'] - $w['y0'];
                if ($current !== null && abs($mid - $current['mid']) <= 0.5 * max($h, $current['h'])) {
                    $lines[$current['i']][] = $w;

                    continue;
                }
                $lines[] = [$w];
                $current = ['i' => array_key_last($lines), 'mid' => $mid, 'h' => $h];
            }
        }

        foreach ($lines as &$line) {
            usort($line, fn ($a, $b) => $a['x0'] <=> $b['x0']);
        }
        unset($line);
        usort($lines, fn ($a, $b) => self::extent($a)[0] <=> self::extent($b)[0]);

        return $lines;
    }

    /** Neighbouring words with a gap smaller than about one letter join into a phrase. */
    private static function phrases(array $line): array
    {
        $gap = 0.8 * self::letterHeight($line);
        $phrases = [];
        foreach ($line as $w) {
            $last = array_key_last($phrases);
            if ($last !== null && $gap > $w['x0'] - $phrases[$last]['x1']) {
                $phrases[$last]['text'] .= ' '.$w['text'];
                $phrases[$last]['x1'] = max($phrases[$last]['x1'], $w['x1']);
                $phrases[$last]['words'][] = $w;
            } else {
                $phrases[] = ['text' => $w['text'], 'x0' => $w['x0'], 'x1' => $w['x1'], 'words' => [$w]];
            }
        }

        return $phrases;
    }

    /** How many lines have a word over each x position (1-unit steps). */
    private static function coverage(array $lines): object
    {
        $min = INF;
        $max = -INF;
        foreach ($lines as $line) {
            foreach ($line as $w) {
                $min = min($min, $w['x0']);
                $max = max($max, $w['x1']);
            }
        }
        $origin = is_finite($min) ? (int) floor($min) : 0;
        $size = is_finite($max) ? (int) ceil($max) - $origin + 2 : 1;

        $diff = array_fill(0, $size + 1, 0);
        foreach ($lines as $line) {
            // One count per line, even where its words overlap.
            $spans = [];
            foreach ($line as $w) {
                $a = (int) floor($w['x0']) - $origin;
                $b = (int) ceil($w['x1']) - $origin;
                if ($spans !== [] && $a <= $spans[array_key_last($spans)][1]) {
                    $spans[array_key_last($spans)][1] = max($spans[array_key_last($spans)][1], $b);
                } else {
                    $spans[] = [$a, $b];
                }
            }
            foreach ($spans as [$a, $b]) {
                $diff[max(0, $a)]++;
                $diff[min($size, max(0, $b))]--;
            }
        }
        $counts = [];
        $run = 0;
        for ($i = 0; $i < $size; $i++) {
            $run += $diff[$i];
            $counts[$i] = $run;
        }

        return new class($counts, $origin)
        {
            public function __construct(private array $counts, private int $origin) {}

            private function at(int $x): int
            {
                return $this->counts[$x - $this->origin] ?? 0;
            }

            /** Fewest lines covering any point between $from and $to. */
            public function min(float $from, float $to): int
            {
                [$a, $b] = [(int) floor(min($from, $to)), (int) ceil(max($from, $to))];
                $least = PHP_INT_MAX;
                for ($x = $a; $x <= $b; $x++) {
                    $least = min($least, $this->at($x));
                }

                return $least === PHP_INT_MAX ? 0 : $least;
            }

            /** Middle of the widest stretch with the fewest covering lines between $from and $to. */
            public function river(float $from, float $to): float
            {
                [$a, $b] = [(int) floor($from), (int) ceil($to)];
                $least = $this->min($a, $b);
                $best = [$a, $a];
                $start = null;
                for ($x = $a; $x <= $b + 1; $x++) {
                    if ($x <= $b && $this->at($x) === $least) {
                        $start ??= $x;

                        continue;
                    }
                    if ($start !== null && $x - 1 - $start > $best[1] - $best[0]) {
                        $best = [$start, $x - 1];
                    }
                    $start = null;
                }

                return ($best[0] + $best[1]) / 2;
            }
        };
    }

    /** @return array{0: float, 1: float, 2: float} top, bottom and median word height of a line */
    private static function extent(array $line): array
    {
        $heights = array_map(fn ($w) => $w['y1'] - $w['y0'], $line);
        sort($heights);

        return [min(array_column($line, 'y0')), max(array_column($line, 'y1')), (float) ($heights[intdiv(count($heights), 2)] ?? 10)];
    }

    private static function isPageMarker(string $text): bool
    {
        return (bool) preg_match('/^(?:page|image)?\s*\d{1,4}\s*(?:of|\/)\s*\d{1,4}$/i', trim($text));
    }
}
