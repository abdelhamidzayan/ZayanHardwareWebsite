<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?string $basePath = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uriPath = rawurldecode(is_string($uriPath) ? $uriPath : '/');

        $base = self::basePath();
        if ($base !== '' && str_starts_with($uriPath, $base)) {
            $uriPath = substr($uriPath, strlen($base));
        }
        if (str_starts_with($uriPath, '/index.php')) {
            $uriPath = substr($uriPath, strlen('/index.php'));
        }

        $path = '/' . trim($uriPath, '/');

        return new self($method, $path, $_GET, $_POST);
    }

    /** Folder the app is served from, e.g. "/zayan-hardware/public" on XAMPP, "" on a virtual host. */
    public static function basePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $url = (string) App::config('app.url', '');
        if ($url !== '') {
            $path = parse_url($url, PHP_URL_PATH);
            return self::$basePath = is_string($path) ? rtrim($path, '/') : '';
        }

        $dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php');

        return self::$basePath = rtrim(str_replace('\\', '/', $dir), '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
