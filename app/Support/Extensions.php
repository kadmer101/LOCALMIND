<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tiny façade over config/extensions.php so views and controllers can check
 * feature flags consistently: Extensions::enabled('export_markdown').
 */
final class Extensions
{
    public static function enabled(string $key): bool
    {
        return (bool) config("extensions.{$key}.enabled", false);
    }

    public static function option(string $key, string $option, mixed $default = null): mixed
    {
        return config("extensions.{$key}.{$option}", $default);
    }

    public static function intOption(string $key, string $option, int $default = 0): int
    {
        $value = config("extensions.{$key}.{$option}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function stringOption(string $key, string $option, string $default = ''): string
    {
        $value = config("extensions.{$key}.{$option}", $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function boolOption(string $key, string $option, bool $default = false): bool
    {
        return (bool) config("extensions.{$key}.{$option}", $default);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        /** @var array<string, array<string, mixed>> $all */
        $all = config('extensions', []);

        return $all;
    }
}
