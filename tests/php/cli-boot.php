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

if (session_status() !== PHP_SESSION_NONE) {
    fwrite(STDERR, "App::boot() must not start a browser session under PHP CLI.\n");
    exit(1);
}

echo "CLI application boot does not start a browser session.\n";

register_shutdown_function(static function () use ($tempRoot): void {
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
});
