<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuthService;

final class Router
{
    /** @var array<int, array{0:string,1:string,2:array|callable,3:bool}> */
    private array $routes = [];

    public function get(string $path, array|callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /** Register a GET route that is intentionally accessible without an account. */
    public function getPublic(string $path, array|callable $handler): void
    {
        $this->add('GET', $path, $handler, true);
    }

    /** Register a POST route that is intentionally accessible without an account. */
    public function postPublic(string $path, array|callable $handler): void
    {
        $this->add('POST', $path, $handler, true);
    }

    public function dispatch(Request $request): Response
    {
        // Public access is declared with the route itself, so route definitions and
        // the guest policy cannot silently drift apart.
        if (AuthService::user() === null && !$this->isGuestRouteAllowed($request)) {
            return Response::redirect(Request::basePath() . '/login', 303);
        }

        $allowed = [];

        foreach ($this->routes as [$method, $regex, $handler]) {
            if (!preg_match($regex, $request->path, $matches)) {
                continue;
            }
            if ($method !== $request->method) {
                $allowed[] = $method;
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            if (isset($params['id'])) {
                $params['id'] = (int) $params['id'];
            }

            return $this->invoke($handler, $request, $params);
        }

        if ($allowed) {
            return new Response('Method Not Allowed', 405, [
                'Allow'        => implode(', ', array_unique($allowed)),
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        return new Response(
            View::render('errors/404', ['title' => 'الصفحة غير موجودة', 'showHeader' => false, 'active' => '']),
            404
        );
    }

    private function isGuestRouteAllowed(Request $request): bool
    {
        foreach ($this->routes as [$method, $regex, $handler, $guestAllowed]) {
            if ($guestAllowed && $method === $request->method
                && preg_match($regex, $request->path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function add(string $method, string $path, array|callable $handler, bool $guestAllowed = false): void
    {
        // {id} matches digits only; any other {name} matches one path segment.
        $regex = preg_replace_callback('#\{(\w+)\}|[^{]+#', static function (array $m): string {
            if (!isset($m[1])) {
                return preg_quote($m[0], '#');
            }
            return '(?P<' . $m[1] . '>' . ($m[1] === 'id' ? '\\d+' : '[^/]+') . ')';
        }, $path);

        $this->routes[] = [$method, '#^' . $regex . '$#', $handler, $guestAllowed];
    }

    private function invoke(array|callable $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $result = (new $class($request))->$method(...$params);
        } else {
            $result = $handler($request, ...$params);
        }

        return $result instanceof Response ? $result : new Response((string) $result);
    }
}
