<?php
declare(strict_types=1);

use App\Core\App;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/app/core/App.php';

$tempRoot = sys_get_temp_dir() . '/zayan-log-redaction-' . bin2hex(random_bytes(6));
$logsDirectory = $tempRoot . '/storage/logs';
if (!mkdir($tempRoot . '/config', 0700, true) || !mkdir($logsDirectory, 0700, true)) {
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

// Prepare a stale log before boot so the retention sweep is exercised.
$staleLog = $logsDirectory . '/app-2020-01-01.log';
file_put_contents($staleLog, "old synthetic log\n");
touch($staleLog, time() - (3 * 86400));
$_ENV['LOG_RETENTION_DAYS'] = '1';

try {
    App::boot($tempRoot);

    if (is_file($staleLog)) {
        throw new RuntimeException('The configured log retention sweep did not remove an expired log.');
    }

    $pushException = new RuntimeException(
        'Push failed for Authorization: Bearer PRIVATE_BEARER_EXCEPTION_SECRET at https://push.example.test/send?token=PRIVATE_URL_TOKEN'
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

    // Exercise raw text paths, not only Throwable objects whose messages are
    // intentionally omitted by exceptionContext().
    App::log('WARNING', 'test.authorization Authorization: Bearer PRIVATE_BEARER_SECRET');
    App::log('WARNING', 'test.cookie Cookie: zayan_session=PRIVATE_COOKIE_SECRET');
    App::log('WARNING', 'test.url https://example.test/callback?access_token=PRIVATE_ACCESS_TOKEN&next=/feed');
    App::log('WARNING', 'test.query https://example.test/callback?token=PRIVATE_QUERY_TOKEN&next=/feed');
    App::log('WARNING', 'test.bearer Bearer PRIVATE_STANDALONE_BEARER');
    App::log('NOTICE', 'test.session session_id=PRIVATE_SESSION_ID');
    App::log('NOTICE', 'test.context.header', [
        'header' => 'Authorization: Bearer PRIVATE_CONTEXT_BEARER',
        'session_id' => 'PRIVATE_SESSION_ID_CONTEXT',
    ]);

    App::log('INFO', 'test.channel.access', ['status' => 404], 'access');
    App::security('test.channel.security', [
        'ip' => '127.0.0.1',
        'csrf_token' => 'PRIVATE_CSRF_SECRET',
    ]);
    App::audit('test.channel.audit', [
        'event_id' => 'synthetic-test',
        'password' => 'PRIVATE_PASSWORD_SECRET',
    ]);

    $channels = ['app', 'access', 'security', 'audit'];
    $contents = '';
    foreach ($channels as $channel) {
        $logPath = $logsDirectory . '/' . $channel . '-' . gmdate('Y-m-d') . '.log';
        if (!is_file($logPath)) {
            throw new RuntimeException('The ' . $channel . ' log channel did not create its daily file.');
        }

        $channelContents = (string) file_get_contents($logPath);
        $contents .= $channelContents;
        $records = array_filter(explode("\n", trim($channelContents)));
        if ($records === []) {
            throw new RuntimeException('The ' . $channel . ' log file is empty.');
        }
        foreach ($records as $record) {
            $decoded = json_decode($record, true);
            if (!is_array($decoded) || ($decoded['channel'] ?? null) !== $channel) {
                throw new RuntimeException('The ' . $channel . ' log contains an invalid JSON record.');
            }
        }
    }

    foreach ([
        'PRIVATE_BEARER_SECRET',
        'PRIVATE_BEARER_EXCEPTION_SECRET',
        'PRIVATE_URL_TOKEN',
        'PRIVATE_CONTEXT_TOKEN',
        'PRIVATE_CONTEXT_BEARER',
        'PRIVATE_DATABASE_SECRET',
        'PRIVATE_COOKIE_SECRET',
        'PRIVATE_ACCESS_TOKEN',
        'PRIVATE_QUERY_TOKEN',
        'PRIVATE_STANDALONE_BEARER',
        'PRIVATE_SESSION_ID',
        'PRIVATE_SESSION_ID_CONTEXT',
        'PRIVATE_CSRF_SECRET',
        'PRIVATE_PASSWORD_SECRET',
    ] as $secret) {
        if (str_contains($contents, $secret)) {
            throw new RuntimeException('A synthetic secret appeared in a log file.');
        }
    }

    foreach ([
        'RuntimeException', 'PDOException', 'HY000', '999', 'test.push.exception',
        'test.authorization', 'test.cookie', 'test.url', 'test.query', 'test.bearer',
        'test.channel.access', 'test.channel.security', 'test.channel.audit', 'test.session', '[REDACTED]',
    ] as $expected) {
        if (!str_contains($contents, $expected)) {
            throw new RuntimeException('A safe exception diagnostic was not recorded: ' . $expected);
        }
    }

    $context = App::exceptionContext($pushException);
    if (array_key_exists('message', $context)) {
        throw new RuntimeException('Exception context must not expose arbitrary message text.');
    }

    echo "Logging channels, retention, and secret-redaction tests passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Logging test failed: " . $error::class . "\n");
    $removeTree($tempRoot);
    exit(1);
}

$removeTree($tempRoot);
