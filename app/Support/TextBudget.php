<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Hard-truncation helper. Small local models on limited RAM must never be fed
 * unbounded text, so every extracted blob passes through here.
 */
final class TextBudget
{
    public const TRUNCATION_MARKER = "\n[...truncated, file was longer]";

    /**
     * Truncate $text to at most $budget characters (multibyte-safe),
     * appending a clear marker when truncation occurred.
     */
    public static function clamp(string $text, int $budget): string
    {
        if ($budget <= 0) {
            return '';
        }

        if (mb_strlen($text) <= $budget) {
            return $text;
        }

        return mb_substr($text, 0, $budget) . self::TRUNCATION_MARKER;
    }
}
