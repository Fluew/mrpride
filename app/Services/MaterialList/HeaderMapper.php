<?php

namespace App\Services\MaterialList;

/**
 * Recognises the columns of a client's material list, whatever their order or wording
 * ("Item Description", "QTY.", "U.O.M", "Preferred Brand", ...).
 *
 * A mapping is "confident" only when both a description column and a quantity column are found.
 * Otherwise the client is asked to map the columns themselves.
 */
class HeaderMapper
{
    /** Fields we extract, with the header wordings recognised for each (already normalised). */
    public const SYNONYMS = [
        'description' => [
            'item', 'items', 'product', 'products', 'material', 'materials', 'description', 'item description',
            'product name', 'product description', 'material description', 'item name', 'particulars', 'name',
            'item details', 'materials description', 'article', 'goods',
        ],
        'quantity' => [
            'quantity', 'qty', 'quantities', 'amount required', 'qty required', 'required qty', 'quantity required',
            'no of units', 'number of units', 'count', 'pcs', 'qty needed', 'order qty', 'req qty', 'nos',
        ],
        'unit' => ['unit', 'units', 'uom', 'unit of measure', 'unit of measurement', 'measure', 'um'],
        'brand' => ['brand', 'manufacturer', 'preferred brand', 'make', 'brand name', 'mfr', 'maker'],
        'specs' => [
            'specifications', 'specification', 'specs', 'spec', 'technical details', 'technical specifications',
            'details', 'rating', 'size', 'model', 'model no', 'type',
        ],
        'unit_price' => ['unit price', 'price', 'cost', 'unit cost', 'rate', 'price per unit', 'srp', 'amount per unit'],
        'remarks' => ['remarks', 'notes', 'note', 'comments', 'comment', 'remark'],
        'item_code' => ['code', 'item code', 'product code', 'sku', 'part no', 'part number', 'catalog no', 'catalogue no', 'ref', 'reference'],
    ];

    public const REQUIRED = ['description', 'quantity'];

    /** Lowercase, drop punctuation (QTY. → qty, U.O.M → uom, Part #  → part), collapse spaces. */
    public static function normalise(mixed $header): string
    {
        $h = mb_strtolower(trim((string) $header));
        $h = str_replace(['#', 'no.'], [' no ', 'no '], $h);
        $h = preg_replace('/(?<=\b[a-z])\.(?=[a-z]\b)/u', '', $h) ?? $h; // u.o.m → uom
        $h = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $h) ?? $h;

        return trim(preg_replace('/\s+/', ' ', $h) ?? $h);
    }

    /** The field a single header names, or null when it is unrelated. */
    public static function fieldFor(mixed $header): ?string
    {
        $h = self::normalise($header);
        if ($h === '' || mb_strlen($h) > 40) {
            return null;
        }

        foreach (self::SYNONYMS as $field => $words) {
            if (in_array($h, $words, true)) {
                return $field;
            }
        }

        // Looser: "qty (pcs)", "brand / make", "item description & specs" — the header *starts*
        // with a known wording. Checked in this order so "unit price" is not taken as "unit".
        foreach (['unit_price', 'quantity', 'description', 'unit', 'brand', 'specs', 'remarks', 'item_code'] as $field) {
            foreach (self::SYNONYMS[$field] as $word) {
                if (str_starts_with($h, $word.' ')) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * Map a header row to {field => column index}. The first column wins when two columns name
     * the same field.
     *
     * @param  array<int, mixed>  $row
     * @return array<string, int>
     */
    public static function map(array $row): array
    {
        $mapping = [];
        foreach (array_values($row) as $i => $cell) {
            $field = self::fieldFor($cell);
            if ($field && ! isset($mapping[$field])) {
                $mapping[$field] = $i;
            }
        }

        return $mapping;
    }

    /** @param  array<string, int>  $mapping */
    public static function isConfident(array $mapping): bool
    {
        return isset($mapping['description'], $mapping['quantity']);
    }

    /**
     * Find the header row in the first rows of a table (titles and blank rows often come first).
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array{index: ?int, mapping: array<string, int>}
     */
    public static function findHeaderRow(array $rows, int $searchRows = 15): array
    {
        $best = ['index' => null, 'mapping' => [], 'score' => 0];

        foreach (array_slice($rows, 0, $searchRows, true) as $i => $row) {
            $mapping = self::map($row);
            $score = count($mapping) + (self::isConfident($mapping) ? 10 : 0);
            if (count($mapping) >= 2 && $score > $best['score']) {
                $best = ['index' => $i, 'mapping' => $mapping, 'score' => $score];
            }
        }

        return ['index' => $best['index'], 'mapping' => $best['mapping']];
    }
}
