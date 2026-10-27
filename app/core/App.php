<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    private static string $root = '';
    private static array $config = [];

    public static function boot(string $root): void
    {
        self::$root = rtrim($root, '/\\');
        self::loadEnv(self::$root . '/.env');

        self::$config = [
            'app'      => require self::$root . '/config/config.php',
            'database' => require self::$root . '/config/database.php',
        ];

        date_default_timezone_set('UTC');
        mb_internal_encoding('UTF-8');

        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        self::registerErrorHandlers();
        self::startSession();
    }

    public static function root(string $path = ''): string
    {
        return self::$root . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => $value,
        };
    }

    /** Dot notation: App::config('app.name'), App::config('database.host'). */
    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    public static function log(string $level, string $message): void
    {
        $dir = self::$root . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] %s: %s\n", gmdate('c'), strtoupper($level), $message);

        // Keep a fallback: Apache may run as a different user and be unable to
        // write to storage/logs even when the directory exists.
        $written = @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log('[Zayan Hardware] ' . rtrim($line));
        }
    }

    private static function loadEnv(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                $_ENV[$key] = $value;
            }
        }
    }

    private static function registerErrorHandlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (\Throwable $e): void {
            self::log('error', sprintf('%s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }

            if (self::config('app.debug', false)) {
                echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
                return;
            }

            try {
                echo View::render('errors/500', ['title' => 'خطأ في الخادم', 'showHeader' => false, 'active' => '']);
            } catch (\Throwable) {
                echo 'Server error';
            }
        });
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) self::config('app.session_name', 'zayan_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
