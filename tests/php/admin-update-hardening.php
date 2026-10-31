<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/app/controllers/AdminUpdateController.php';
$source = file_get_contents($path);
if (!is_string($source)) {
    fwrite(STDERR, "Unable to read AdminUpdateController.\n");
    exit(1);
}

$required = [
    'Csrf::verify(',
    "RateLimiter::allowForUser('admin.site_update'",
    'AdminRosterService::assertActorActive(',
    "GET_LOCK('zayan_hardware_admin_deletion_request', 5)",
    "RELEASE_LOCK('zayan_hardware_admin_deletion_request')",
    "private const UPDATE_SCRIPT = '/home/a/bin/zayan-hardware-update.sh';",
    "private const PROJECT_DIR = '/opt/lampp/htdocs/zayan_hardware';",
    'proc_open($command, $descriptors, $pipes, self::PROJECT_DIR)',
    "App::audit('admin.site_update.started'",
    "App::audit('admin.site_update.completed'",
];
$missing = [];
foreach ($required as $marker) {
    if (!str_contains($source, $marker)) $missing[] = $marker;
}

// The command must remain a fixed allow-listed argv array. Do not concatenate
// POST/query data into the shell invocation.
$commandStart = strpos($source, '$command = [');
$commandEnd = $commandStart === false ? false : strpos($source, '];', $commandStart);
if ($commandStart === false || $commandEnd === false) {
    $missing[] = 'fixed update command array';
} else {
    $commandBlock = substr($source, $commandStart, $commandEnd - $commandStart);
    if (str_contains($commandBlock, '$this->request->input') || str_contains($commandBlock, '$_POST')) {
        $missing[] = 'update command must not include request-controlled arguments';
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Admin update hardening check failed: " . implode('; ', $missing) . "\n");
    exit(1);
}

echo "Admin site update hardening check passed.\n";
