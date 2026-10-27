<?php
declare(strict_types=1);

use App\Core\App;

return [
    'host' => (string) App::env('DB_HOST', '127.0.0.1'),
    'port' => (int) App::env('DB_PORT', 3306),
    'name' => (string) App::env('DB_NAME', 'zayan_hardware'),
    'user' => (string) App::env('DB_USER', 'root'),
    'pass' => (string) App::env('DB_PASS', ''),
];
