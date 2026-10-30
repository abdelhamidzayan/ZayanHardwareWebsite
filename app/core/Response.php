<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private ?\Closure $streamCallback = null;

    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function json(array $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return new self($body, $status, ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    public static function stream(callable $callback, int $status = 200, array $headers = []): self
    {
        $response = new self('', $status, $headers);
        $response->streamCallback = \Closure::fromCallable($callback);
        return $response;
    }

    /** Stream a verified file or byte range in bounded-memory chunks. */
    public static function streamFile(
        string $path,
        int $offset,
        int $length,
        int $status = 200,
        array $headers = []
    ): self {
        return self::stream(static function () use ($path, $offset, $length): void {
            $handle = @fopen($path, 'rb');
            if ($handle === false) return;
            if ($offset > 0 && fseek($handle, $offset) !== 0) {
                fclose($handle);
                return;
            }

            $remaining = max(0, $length);
            while ($remaining > 0 && connection_status() === CONNECTION_NORMAL) {
                $chunk = fread($handle, min(1024 * 1024, $remaining));
                if (!is_string($chunk) || $chunk === '') break;
                echo $chunk;
                $remaining -= strlen($chunk);
                if (ob_get_level() > 0) @ob_flush();
                flush();
            }
            fclose($handle);
        }, $status, $headers);
    }

    public function send(): void
    {
        http_response_code($this->status);

        $headers = $this->headers + [
            'Content-Type'           => 'text/html; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'X-Frame-Options'        => 'SAMEORIGIN',
        ];
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        if ($this->streamCallback !== null) {
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            ($this->streamCallback)();
            return;
        }

        echo $this->body;
    }
}
