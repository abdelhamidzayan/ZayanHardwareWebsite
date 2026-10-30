<?php
declare(strict_types=1);

use App\Core\App;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/app/core/App.php';

if (session_status() !== PHP_SESSION_NONE) {
    fwrite(STDERR, "CLI test must start without an active session.\n");
    exit(1);
}

$tempRoot = sys_get_temp_dir() . '/zayan-cli-boot-' . bin2hex(random_bytes(6));
if (!mkdir($tempRoot . '/config', 0700, true)) {
    fwrite(STDERR, "Unable to create an isolated CLI boot directory.\n");
    exit(1);
}
copy($projectRoot . '/config/config.php', $tempRoot . '/config/config.php');
copy($projectRoot . '/config/database.php', $tempRoot . '/config/database.php');

App::boot($tempRoot);

// A user with no custom avatar may never have created storage/private.
require_once $projectRoot . '/app/services/AccountRetentionService.php';
$avatarCleanup = new ReflectionMethod(\\App\\Services\\AccountRetentionService::class, 'removePrivateAvatarFiles');
$avatarCleanup->setAccessible(true);
$avatarCleanup->invoke(null, 123);
if (file_exists($tempRoot . '/storage/private')) {
    fwrite(STDERR, "Avatar cleanup must not create private storage for an account without a custom avatar.\\n");
    exit(1);
}

if (session_status() !== PHP_SESSION_NONE) {
    fwrite(STDERR, "App::boot() must not start a browser session under PHP CLI.\n");
    exit(1);
}

echo "CLI application boot does not start a browser session.\n";

register_shutdown_function(static function () use ($tempRoot): void {
    // App's shutdown logger was registered first; inspect its output here.
    $unexpectedAccessLogs = glob($tempRoot . '/storage/logs/access-*.log') ?: [];

    $removeTree = static function (string $path) use (&$removeTree): void {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }
        @rmdir($path);
    };
    $removeTree($tempRoot);

    if ($unexpectedAccessLogs) {
        fwrite(STDERR, "CLI boot must not write an HTTP access log.\n");
        exit(1);
    }
});
