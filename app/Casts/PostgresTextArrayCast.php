<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a native PostgreSQL `text[]` column to/from a PHP array.
 *
 * Phase 5.1 root cause (2026-08-28 production 500 on "Save changes"):
 * `patients.known_allergies` is a native Postgres array column
 * (udt_name `_text`), NOT jsonb — confirmed live via
 * information_schema.columns. Eloquent's built-in 'array' cast always
 * serializes with json_encode()/json_decode(), which is correct for a
 * jsonb column (e.g. `emergency_contact`) but wrong for a native array
 * column, whose wire format is Postgres's own array literal syntax
 * (`{a,b}`), not JSON (`["a","b"]`). Postgres's array_in() parser
 * rejects a leading `[` outright, which is exactly the production
 * error that was reproduced from Render logs:
 *   SQLSTATE[22P02] malformed array literal: "[]"
 *   DETAIL: "[" must introduce explicitly-specified array dimensions.
 * On the read side the same mismatch silently breaks display (a `{...}`
 * value fails json_decode() and returns null), it just wasn't visible
 * yet because known_allergies happened to already be null in the row
 * that was tested. This cast fixes both directions with one
 * self-consistent implementation, scoped to this single column.
 */
class PostgresTextArrayCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        $value = trim((string) $value);

        if ($value === '{}' || $value === '') {
            return [];
        }

        // Strip the outer braces, then split on commas that are not
        // inside a double-quoted element. Postgres double-quotes any
        // element containing a comma, brace, backslash, or double quote,
        // and backslash-escapes embedded quotes/backslashes within it.
        $inner = substr($value, 1, -1);

        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|([^,]+)/', $inner, $matches, PREG_SET_ORDER);

        $result = [];

        foreach ($matches as $match) {
            if ($match[0] !== '' && $match[0][0] === '"') {
                $result[] = stripcslashes($match[1]);
            } elseif (isset($match[2]) && trim($match[2]) !== '') {
                $result[] = $match[2];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $items = is_array($value) ? $value : [$value];

        $escaped = array_map(function ($item) {
            $item = (string) $item;
            $item = str_replace('\\', '\\\\', $item);
            $item = str_replace('"', '\\"', $item);

            return '"'.$item.'"';
        }, $items);

        return '{'.implode(',', $escaped).'}';
    }
}
