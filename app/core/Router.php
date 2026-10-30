<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\AuthService;

final class Router
{
    /** Route placeholders whose controller parameters are declared as integers. */
    private const INTEGER_ROUTE_PARAMETERS = ['id', 'userId'];

    /** @var array<int, array{0:string,1:string,2:array|callable}> */
    private array $routes = [];

    public function get(string $path, array|callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        // Guests may only use the authentication screens and public help/policy pages.
        // Enforce this before route matching so direct URLs and POST endpoints are protected too.
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

            $params = $this->normalizeParams(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

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
        $allowedRoutes = [
            'GET /',
            'GET /login',
            'POST /login',
            'GET /register',
            'POST /register',
            'GET /register/usernames',
            'GET /help',
            'GET /policies',
        ];

        return in_array($request->method . ' ' . $request->path, $allowedRoutes, true);
    }

    /** Convert only explicitly declared integer route placeholders; UUID/text IDs remain strings. */
    private function normalizeParams(array $params): array
    {
        foreach (self::INTEGER_ROUTE_PARAMETERS as $name) {
            if (isset($params[$name])) {
                $params[$name] = (int) $params[$name];
            }
        }

        return $params;
    }

    private function add(string $method, string $path, array|callable $handler): void
    {
        // Integer placeholders listed above match digits only; other placeholders match one path segment.
        $regex = preg_replace_callback('#\{(\w+)\}|[^{]+#', static function (array $m): string {
            if (!isset($m[1])) {
                return preg_quote($m[0], '#');
            }
            $pattern = in_array($m[1], self::INTEGER_ROUTE_PARAMETERS, true) ? '\d+' : '[^/]+';
            return '(?P<' . $m[1] . '>' . $pattern . ')';
        }, $path);

        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
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
