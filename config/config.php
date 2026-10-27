<?php
declare(strict_types=1);

use App\Core\App;

return [
    'name'         => (string) App::env('APP_NAME', 'Hardware Tech'),
    'env'          => (string) App::env('APP_ENV', 'production'),
    'debug'        => (bool) App::env('APP_DEBUG', false),
    'url'          => (string) App::env('APP_URL', ''),
    'lang'         => (string) App::env('APP_LANG', 'ar'),
    'dir'          => (string) App::env('APP_DIR', 'rtl'),
    'session_name' => (string) App::env('SESSION_NAME', 'zayan_session'),
];
