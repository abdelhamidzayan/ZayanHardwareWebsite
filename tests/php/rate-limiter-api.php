<?php
declare(strict_types=1);

use App\Services\RateLimiter;

require dirname(__DIR__, 2) . '/app/services/RateLimiter.php';

$requiredMethods = ['allowForUser', 'allowForIp', 'attemptForIpAndIdentifier'];
foreach ($requiredMethods as $method) {
    if (!method_exists(RateLimiter::class, $method)) {
        fwrite(STDERR, "Missing RateLimiter API: {$method}\n");
        exit(1);
    }
}

$root = dirname(__DIR__, 2);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)
);
$legacyCalls = [];
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $source = file_get_contents($file->getPathname());
    if (!is_string($source)) continue;
    if (preg_match('/RateLimiter::(?:tooMany|hit)\\s*\\(/', $source) === 1) {
        $legacyCalls[] = $file->getPathname();
    }
}
if ($legacyCalls) {
    fwrite(STDERR, "Legacy RateLimiter API calls found:\n" . implode("\n", $legacyCalls) . "\n");
    exit(1);
}

echo "RateLimiter API references are consistent.\n";
