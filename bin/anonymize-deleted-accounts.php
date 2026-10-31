<?php
declare(strict_types=1);

$root = dirname(__DIR__);
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $map = [
            'App\Core\\' => '/app/core/',
            'App\Controllers\\' => '/app/controllers/',
            'App\Models\\' => '/app/models/',
            'App\Services\\' => '/app/services/',
            'App\Middleware\\' => '/app/middleware/',
        ];
        foreach ($map as $prefix => $directory) {
            if (str_starts_with($class, $prefix)) {
                $file = $root . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) require $file;
                return;
            }
        }
    });
}

use App\Core\App;
use App\Services\AccountRetentionService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$options = getopt('', ['execute', 'limit:', 'help']);
if (isset($options['help'])) {
    echo "Usage: php bin/anonymize-deleted-accounts.php [--execute] [--limit=100]\n";
    echo "Default mode is dry-run. --execute anonymizes account identity and preserves all content.\n";
    exit(0);
}

$limit = isset($options['limit']) ? filter_var($options['limit'], FILTER_VALIDATE_INT) : 100;
if ($limit === false || $limit < 1 || $limit > 500) {
    fwrite(STDERR, "Limit must be an integer between 1 and 500.\n");
    exit(2);
}

try {
    App::boot($root);
    $result = AccountRetentionService::run(isset($options['execute']), (int) $limit);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(!empty($result['errors']) ? 1 : 0);
} catch (Throwable $e) {
    $safeContext = App::exceptionContext($e);
    fwrite(STDERR, 'Account retention failed: '
        . json_encode($safeContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . PHP_EOL);
    exit(1);
}
