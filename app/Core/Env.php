<?php
declare(strict_types=1);

namespace UMP\Core;

final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $value = trim($value, $value[0]);
            } else {
                $value = trim((string) preg_replace('/\s+#.*$/', '', $value));
            }
            self::$vars[trim($key)] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        // A real environment variable wins over .env (standard for servers and containers).
        $real = getenv($key);
        $v = ($real !== false && $real !== '') ? $real : (self::$vars[$key] ?? null);
        return ($v === null || $v === '') ? $default : $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::$vars[$key] ?? null;
        return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }
}
