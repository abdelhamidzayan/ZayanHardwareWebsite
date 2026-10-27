<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    protected function view(string $view, array $data = [], int $status = 200, string $layout = 'app'): Response
    {
        return new Response(View::render($view, $data, $layout), $status);
    }

    /** Redirect to an internal path such as "/feed" (never to an external URL). */
    protected function redirect(string $path): Response
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            $path = '/';
        }

        return Response::redirect(Request::basePath() . $path);
    }

    protected function notFound(): Response
    {
        return new Response(
            View::render('errors/404', ['title' => 'الصفحة غير موجودة', 'showHeader' => false, 'active' => '']),
            404
        );
    }

    protected function flash(string $type, string $message): void
    {
        View::addFlash($type, $message);
    }
}
