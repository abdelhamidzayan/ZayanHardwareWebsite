<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuthService;

final class View
{
    /**
     * Render app/views/{$view}.php inside app/views/layouts/{$layout}.php.
     * $data feeds both the view and the layout (title, active, showHeader, ...).
     */
    public static function render(string $view, array $data = [], string $layout = 'app'): string
    {
        $vars    = $data + self::shared();
        $content = self::capture('views/' . $view, $vars);

        if ($layout === '') {
            return $content;
        }

        return self::capture('views/layouts/' . $layout, ['content' => $content] + $vars);
    }

    public static function addFlash(string $type, string $message): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
        }
    }

    private static function shared(): array
    {
        $flash = [];
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
        }

        return [
            'appName' => (string) App::config('app.name', 'Hardware Tech'),
            'baseUrl' => Request::basePath(),
            'lang'    => (string) App::config('app.lang', 'ar'),
            'dir'     => (string) App::config('app.dir', 'rtl'),
            'flash'   => $flash,
            'e'       => static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            'csrf'    => static fn (): string => Csrf::field(),
            'currentUser' => AuthService::user(),
        ];
    }

    private static function capture(string $name, array $vars): string
    {
        if (!preg_match('#^[a-z0-9_/-]+$#i', $name)) {
            throw new \InvalidArgumentException('Invalid view name.');
        }

        $file = App::root('app/' . $name . '.php');
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $name);
        }

        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
