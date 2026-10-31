<?php
declare(strict_types=1);

use App\Core\App;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/app/core/App.php';

$tempRoot = sys_get_temp_dir() . '/zayan-log-redaction-' . bin2hex(random_bytes(6));
if (!mkdir($tempRoot . '/config', 0700, true)) {
    fwrite(STDERR, "Unable to create the isolated logging test directory.\n");
    exit(1);
}

copy($projectRoot . '/config/config.php', $tempRoot . '/config/config.php');
copy($projectRoot . '/config/database.php', $tempRoot . '/config/database.php');

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

try {
    App::boot($tempRoot);

    $pushException = new RuntimeException(
        'Push failed for Authorization: Bearer PRIVATE_BEARER_SECRET at https://push.example.test/send?token=PRIVATE_URL_TOKEN'
    );
    App::logException('WARNING', 'test.push.exception', $pushException, [
        'user_id' => 42,
        'token' => 'PRIVATE_CONTEXT_TOKEN',
    ]);
    App::log('ERROR', 'test.throwable.context', ['failure' => $pushException]);

    $databaseException = new PDOException(
        'SQLSTATE[HY000]: driver detail contains PRIVATE_DATABASE_SECRET'
    );
    $databaseException->errorInfo = ['HY000', 999, 'PRIVATE_DATABASE_SECRET'];
    App::logException('ERROR', 'test.database.exception', $databaseException);

    $logPath = $tempRoot . '/storage/logs/app-' . gmdate('Y-m-d') . '.log';
    $contents = is_file($logPath) ? (string) file_get_contents($logPath) : '';

    foreach ([
        'PRIVATE_BEARER_SECRET',
        'PRIVATE_URL_TOKEN',
        'PRIVATE_CONTEXT_TOKEN',
        'PRIVATE_DATABASE_SECRET',
    ] as $secret) {
        if (str_contains($contents, $secret)) {
            throw new RuntimeException('A synthetic secret appeared in the application log.');
        }
    }

    foreach (['RuntimeException', 'PDOException', 'HY000', '999', 'test.push.exception'] as $expected) {
        if (!str_contains($contents, $expected)) {
            throw new RuntimeException('A safe exception diagnostic was not recorded: ' . $expected);
        }
    }

    $context = App::exceptionContext($pushException);
    if (array_key_exists('message', $context)) {
        throw new RuntimeException('Exception context must not expose arbitrary message text.');
    }

    echo "Exception logging redaction test passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Exception logging redaction test failed: " . $error::class . "\n");
    exitCode:
    $removeTree($tempRoot);
    exit(1);
}

$removeTree($tempRoot);
