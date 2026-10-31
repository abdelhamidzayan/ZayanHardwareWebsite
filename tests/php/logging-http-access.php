<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$tempRoot = sys_get_temp_dir() . '/zayan-http-access-log-' . bin2hex(random_bytes(6));
$process = null;
$pipes = [];

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
    if (!mkdir($tempRoot . '/config', 0700, true)
        || !mkdir($tempRoot . '/storage/logs', 0700, true)) {
        throw new RuntimeException('Unable to create isolated HTTP logging test directories.');
    }

    if (!copy($projectRoot . '/config/config.php', $tempRoot . '/config/config.php')
        || !copy($projectRoot . '/config/database.php', $tempRoot . '/config/database.php')) {
        throw new RuntimeException('Unable to copy isolated application configuration.');
    }

    $bootstrap = var_export($projectRoot . '/app/core/App.php', true);
    $root = var_export($tempRoot, true);
    $router = $tempRoot . '/router.php';
    $routerCode = "<?php\n"
        . "require {$bootstrap};\n"
        . "\\App\\Core\\App::boot({$root});\n"
        . "http_response_code(418);\n"
        . "header('Content-Type: text/plain; charset=utf-8');\n"
        . "echo 'access-log-probe';\n";
    if (file_put_contents($router, $routerCode) === false) {
        throw new RuntimeException('Unable to create the isolated HTTP router.');
    }

    // Ask the OS for an unused local port, then release it before starting PHP.
    $reservation = stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketMessage);
    if ($reservation === false) {
        throw new RuntimeException('Unable to reserve a local test port.');
    }
    $socketName = stream_socket_get_name($reservation, false);
    fclose($reservation);
    if (!is_string($socketName) || !preg_match('/:(\d+)$/', $socketName, $portMatch)) {
        throw new RuntimeException('Unable to determine the isolated HTTP test port.');
    }
    $port = (int) $portMatch[1];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
        $descriptors,
        $pipes,
        $projectRoot
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the isolated PHP HTTP server.');
    }
    if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
    if (isset($pipes[1]) && is_resource($pipes[1])) stream_set_blocking($pipes[1], false);
    if (isset($pipes[2]) && is_resource($pipes[2])) stream_set_blocking($pipes[2], false);

    $url = 'http://127.0.0.1:' . $port . '/__logging_probe?token=PRIVATE_ACCESS_LOG_TOKEN';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'ignore_errors' => true,
            'timeout' => 2,
            'header' => "Connection: close\r\n",
        ],
    ]);

    $response = false;
    $responseHeaders = [];
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $response = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        if (is_string($response)) break;
        usleep(100000);
    }

    if (!is_string($response) || $response !== 'access-log-probe') {
        throw new RuntimeException('The isolated HTTP request did not receive its expected response.');
    }

    $statusLine = (string) ($responseHeaders[0] ?? '');
    if (!preg_match('/\s418\s/', $statusLine)) {
        throw new RuntimeException('The isolated HTTP response did not preserve its expected status code.');
    }
} catch (Throwable $error) {
    if (is_resource($process)) {
        @proc_terminate($process);
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) @fclose($pipe);
        }
        @proc_close($process);
        $process = null;
    }
    $removeTree($tempRoot);
    fwrite(STDERR, 'HTTP access logging test failed: ' . $error::class . ' — ' . $error->getMessage() . "\n");
    exit(1);
}

if (is_resource($process)) {
    @proc_terminate($process);
    foreach ($pipes as $pipe) {
        if (is_resource($pipe)) @fclose($pipe);
    }
    @proc_close($process);
}

try {
    $logPath = $tempRoot . '/storage/logs/access-' . gmdate('Y-m-d') . '.log';
    if (!is_file($logPath)) {
        throw new RuntimeException('The HTTP shutdown handler did not create the access log.');
    }

    $records = array_filter(explode("\n", trim((string) file_get_contents($logPath))));
    $matched = false;
    foreach ($records as $line) {
        $record = json_decode($line, true);
        if (!is_array($record) || ($record['message'] ?? null) !== 'http.request.completed') {
            continue;
        }

        $context = $record['context'] ?? [];
        if (($context['method'] ?? null) !== 'GET'
            || ($context['path'] ?? null) !== '/__logging_probe'
            || (int) ($context['status'] ?? 0) !== 418
            || !is_string($record['request_id'] ?? null)
            || $record['request_id'] === '') {
            throw new RuntimeException('The access record did not contain the expected request metadata.');
        }
        $matched = true;
    }

    if (!$matched) {
        throw new RuntimeException('No completed HTTP request record was found.');
    }

    $contents = (string) file_get_contents($logPath);
    if (str_contains($contents, 'PRIVATE_ACCESS_LOG_TOKEN')) {
        throw new RuntimeException('The access log exposed a synthetic query token.');
    }

    echo "HTTP access shutdown logging test passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'HTTP access logging test failed: ' . $error::class . ' — ' . $error->getMessage() . "\n");
    $removeTree($tempRoot);
    exit(1);
}

$removeTree($tempRoot);
