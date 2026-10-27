<?php
declare(strict_types=1);

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    // Fallback when Composer has not been run yet (same map as composer.json).
    spl_autoload_register(static function (string $class) use ($root): void {
        $map = [
            'App\\Core\\'        => '/app/core/',
            'App\\Controllers\\' => '/app/controllers/',
            'App\\Models\\'      => '/app/models/',
            'App\\Services\\'    => '/app/services/',
            'App\\Middleware\\'  => '/app/middleware/',
        ];
        foreach ($map as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $root . $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                }
                return;
            }
        }
    });
}

use App\Core\App;
use App\Core\Request;
use App\Core\Router;

App::boot($root);

$router = new Router();
(require $root . '/config/routes.php')($router);

$router->dispatch(Request::capture())->send();
