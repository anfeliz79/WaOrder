<?php

namespace App\Services\Menu;

class MenuSearcher
{
    /**
     * Find the best matching item using fuzzy search.
     * Returns the item or null if no good match found.
     */
    public static function fuzzyMatch(string $input, array $items, int $threshold = 3): ?array
    {
        $input = self::normalize($input);
        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($items as $item) {
            $name = self::normalize($item['name']);

            // Exact match
            if ($name === $input) {
                return $item;
            }

            // Contains match
            if (str_contains($name, $input) || str_contains($input, $name)) {
                return $item;
            }

            // Levenshtein distance
            $distance = levenshtein($input, $name);
            if ($distance < $bestDistance && $distance <= $threshold) {
                $bestDistance = $distance;
                $bestMatch = $item;
            }
        }

        return $bestMatch;
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        // Remove accents
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
            $text
        );
        return $text;
    }
}
