<?php
/**
 * Tiny .env loader (no dependencies). Real environment variables win over the file.
 * Usage: env('APP_ENV', 'production'); env_bool('ANALYTICS_ENABLED', true); env_int('RATE_LIMIT_PER_HOUR', 8);
 * The file lives in the site root as `.env` (never committed, never served: .htaccess blocks it).
 */
declare(strict_types=1);

function env_load(): array {
    static $vars = null;
    if ($vars !== null) return $vars;
    $vars = [];
    $f = dirname(__DIR__) . '/.env';
    if (is_file($f)) {
        foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $line, 2));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $k)) continue;
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) $v = substr($v, 1, -1);
            else $v = trim((string)preg_replace('/\s+#.*$/', '', $v));
            $vars[$k] = $v;
        }
    }
    return $vars;
}

function env(string $key, ?string $default = null): ?string {
    $real = getenv($key);
    if ($real !== false && $real !== '') return (string)$real;
    $v = env_load()[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}
function env_bool(string $key, bool $default = false): bool {
    $v = env($key);
    return $v === null ? $default : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
}
function env_int(string $key, int $default): int {
    $v = env($key);
    return $v !== null && is_numeric($v) ? (int)$v : $default;
}
