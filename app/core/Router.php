<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
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

    private function add(string $method, string $path, array|callable $handler): void
    {
        // {id} matches digits only; any other {name} matches one path segment.
        $regex = preg_replace_callback('#\{(\w+)\}|[^{]+#', static function (array $m): string {
            if (!isset($m[1])) {
                return preg_quote($m[0], '#');
            }
            return '(?P<' . $m[1] . '>' . ($m[1] === 'id' ? '\d+' : '[^/]+') . ')';
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
