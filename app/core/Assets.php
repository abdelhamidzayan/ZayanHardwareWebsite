<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Builds cache-busted URLs for public static assets.
 *
 * Every stylesheet/script the app links is served with a long cache lifetime
 * (see public/.htaccess). To make that safe, each URL carries a ?v= marker
 * derived from the file's modification time, so any deployed change produces
 * a new URL and browsers fetch the fresh file immediately.
 */
final class Assets
{
    /** Per-request cache of resolved versions to avoid repeated stat calls. */
    private static array $versions = [];

    /**
     * Return "{$baseUrl}{$path}?v={version}" for an existing public asset,
     * or the plain URL when the file cannot be resolved (never breaks pages).
     */
    public static function url(string $baseUrl, string $path): string
    {
        $version = self::version($path);

        return $baseUrl . $path . ($version !== '' ? '?v=' . $version : '');
    }

    private static function version(string $path): string
    {
        if (array_key_exists($path, self::$versions)) {
            return self::$versions[$path];
        }

        $version = '';
        // Only resolve simple, self-contained paths inside public/.
        if (preg_match('#^/[a-z0-9_][a-z0-9_./-]*$#iD', $path) === 1 && !str_contains($path, '..')) {
            $file = App::root('public' . $path);
            if (is_file($file)) {
                $version = dechex((int) filemtime($file));
            }
        }

        return self::$versions[$path] = $version;
    }
}
