<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    private static string $root = '';
    private static array $config = [];
    private static string $requestId = '';
    private static float $requestStartedAt = 0.0;

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

        self::$requestId = bin2hex(random_bytes(8));
        self::$requestStartedAt = microtime(true);

        // Never expose PHP errors to visitors. Production should also use APP_DEBUG=false.
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        self::registerErrorHandlers();
        self::pruneOldLogs();
        self::registerShutdownHandler();
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

    public static function requestId(): string
    {
        return self::$requestId;
    }

    /**
     * Write one JSON log record. Separate channels make it easier to inspect
     * application errors, HTTP requests, security events, and audit events.
     */
    public static function log(
        string $level,
        string $message,
        array $context = [],
        string $channel = 'app'
    ): void {
        $channel = in_array($channel, ['app', 'access', 'security', 'audit'], true) ? $channel : 'app';
        $directory = self::$root . '/storage/logs';

        try {
            if (self::$root === '') {
                error_log('[Zayan Hardware] ' . self::redact($message));
                return;
            }

            if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
                error_log('[Zayan Hardware logger] Cannot create storage/logs');
                return;
            }

            $record = [
                'timestamp'  => gmdate('Y-m-d\TH:i:s\Z'),
                'level'      => strtoupper($level),
                'channel'    => $channel,
                'request_id' => self::$requestId !== '' ? self::$requestId : null,
                'message'    => self::redact($message),
                'context'    => self::sanitizeLogValue($context),
            ];

            $line = json_encode(
                $record,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
            );

            if (!is_string($line)) {
                error_log('[Zayan Hardware logger] Could not encode log record');
                return;
            }

            $file = $directory . '/' . $channel . '-' . gmdate('Y-m-d') . '.log';
            $written = @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);

            if ($written === false) {
                error_log('[Zayan Hardware logger] Cannot write log file: ' . $file);
                return;
            }

            @chmod($directory, 0770);
            @chmod($file, 0660);
        } catch (\Throwable $e) {
            // Do not let a logging failure take the website down.
            error_log('[Zayan Hardware logger failure] ' . get_class($e));
        }
    }

    public static function security(string $event, array $context = []): void
    {
        self::log('NOTICE', $event, $context, 'security');
    }

    public static function audit(string $event, array $context = []): void
    {
        self::log('INFO', $event, $context, 'audit');
    }

    /**
     * Build a safe exception context without copying arbitrary exception messages.
     * PDO errors are represented by SQLSTATE and driver code only.
     */
    public static function exceptionContext(\Throwable $exception): array
    {
        $context = ['exception' => $exception::class];

        if ($exception instanceof \PDOException) {
            if (isset($exception->errorInfo[0])) {
                $context['sql_state'] = (string) $exception->errorInfo[0];
            }
            if (isset($exception->errorInfo[1])) {
                $context['driver_code'] = (int) $exception->errorInfo[1];
            }
        } elseif ($exception instanceof \ErrorException) {
            $context['severity'] = $exception->getSeverity();
        }

        $code = $exception->getCode();
        if ((is_int($code) || (is_string($code) && ctype_digit($code))) && (int) $code !== 0) {
            $context['exception_code'] = (int) $code;
        }

        return $context;
    }

    /** Log exception type and safe diagnostic codes, never its arbitrary message. */
    public static function logException(
        string $level,
        string $event,
        \Throwable $exception,
        array $context = [],
        string $channel = 'app'
    ): void {
        self::log($level, $event, array_merge(self::exceptionContext($exception), $context), $channel);
    }

    private static function sanitizeLogValue(mixed $value, string $key = '', int $depth = 0): mixed
    {
        if ($key !== '' && preg_match('/password|passwd|token|secret|authorization|cookie|csrf|api[_-]?key|private[_-]?key/i', $key)) {
            return '[REDACTED]';
        }

        if ($depth > 5) {
            return '[MAX_DEPTH]';
        }

        if (is_array($value)) {
            $clean = [];
            $count = 0;
            foreach ($value as $childKey => $childValue) {
                if ($count++ >= 50) {
                    $clean['_truncated'] = true;
                    break;
                }
                $clean[$childKey] = self::sanitizeLogValue(
                    $childValue,
                    is_string($childKey) ? $childKey : '',
                    $depth + 1
                );
            }
            return $clean;
        }

        if (is_string($value)) {
            return self::redact(mb_substr($value, 0, 2000));
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if ($value instanceof \Throwable) {
            return self::exceptionContext($value);
        }

        return '[' . get_debug_type($value) . ']';
    }

    private static function redact(string $value): string
    {
        $pattern = "/\\b(password|passwd|token|secret|authorization|cookie|csrf|api[_-]?key)\\b\\s*[:=]\\s*(\"[^\"]*\"|'[^']*'|[^\\s,;]+)/i";
        return preg_replace($pattern, '$1=[REDACTED]', $value) ?? $value;
    }

    private static function pruneOldLogs(): void
    {
        $directory = self::$root . '/storage/logs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0770, true);
        }
        if (!is_dir($directory)) {
            return;
        }

        $today = gmdate('Y-m-d');
        $marker = $directory . '/.retention-check';
        if (is_file($marker) && trim((string) @file_get_contents($marker)) === $today) {
            return;
        }

        $days = max(1, min(3650, (int) self::env('LOG_RETENTION_DAYS', 30)));
        $cutoff = time() - ($days * 86400);

        foreach (glob($directory . '/*.log') ?: [] as $file) {
            if (is_file($file) && (int) @filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }

        @file_put_contents($marker, $today, LOCK_EX);
        @chmod($marker, 0660);
    }

    private static function registerErrorHandlers(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            $level = match ($severity) {
                E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR => 'ERROR',
                E_WARNING, E_USER_WARNING                 => 'WARNING',
                E_NOTICE, E_USER_NOTICE, E_STRICT,
                E_DEPRECATED, E_USER_DEPRECATED             => 'NOTICE',
                default                                   => 'WARNING',
            };

            self::log($level, 'php.error', [
                'severity' => $severity,
                'message'  => $message,
                'file'     => $file,
                'line'     => $line,
            ]);

            // Preserve the project's previous behavior: reported PHP warnings
            // become exceptions so they cannot silently corrupt an operation.
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (\Throwable $e): void {
            $exceptionContext = array_merge(
                self::exceptionContext($e),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            self::log('ERROR', 'app.uncaught_exception', $exceptionContext);

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

    private static function registerShutdownHandler(): void
    {
        register_shutdown_function(static function (): void {
            $lastError = error_get_last();
            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

            if (is_array($lastError) && in_array($lastError['type'] ?? 0, $fatalTypes, true)) {
                self::log('CRITICAL', 'php.fatal_error', [
                    'severity' => $lastError['type'],
                    'message'  => $lastError['message'] ?? 'Unknown fatal error',
                    'file'     => $lastError['file'] ?? '',
                    'line'     => $lastError['line'] ?? 0,
                ]);
            }

            // CLI workers have no HTTP request to report in the access channel.
            if (PHP_SAPI === 'cli') {
                return;
            }

            $status = http_response_code();
            if (!is_int($status) || $status < 100) {
                $status = 200;
            }

            $uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            $path = is_string($uriPath) ? substr($uriPath, 0, 512) : '/';
            $duration = self::$requestStartedAt > 0
                ? round((microtime(true) - self::$requestStartedAt) * 1000, 2)
                : null;

            self::log(
                $status >= 500 ? 'ERROR' : ($status >= 400 ? 'WARNING' : 'INFO'),
                'http.request.completed',
                [
                    'method'       => strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI')),
                    'path'         => $path,
                    'status'       => $status,
                    'duration_ms'  => $duration,
                    'user_id'      => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
                ],
                'access'
            );
        });
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

    private static function startSession(): void
    {
        // CLI workers do not need browser sessions or session cookies.
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
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
