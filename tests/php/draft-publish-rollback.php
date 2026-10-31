<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/DraftPublishingService.php';

use App\Services\DraftPublishingService;

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$root = sys_get_temp_dir() . '/zayan-draft-rollback-' . bin2hex(random_bytes(6));
$sourceDirectory = $root . '/drafts';
$destinationDirectory = $root . '/issues';
if (!mkdir($sourceDirectory, 0700, true) || !mkdir($destinationDirectory, 0700, true)) {
    throw new RuntimeException('Unable to create rollback test directories');
}

$source = $sourceDirectory . '/media.bin';
$destination = $destinationDirectory . '/media.bin';
file_put_contents($destination, 'preserve-this-media');

$failures = DraftPublishingService::restoreMovedFiles([
    ['from' => $source, 'to' => $destination],
]);

expect($failures === [], 'A normal rollback should restore every moved file.');
expect(is_file($source), 'The original draft file should be restored.');
expect(!file_exists($destination), 'The moved destination should no longer exist after a successful restore.');
expect(file_get_contents($source) === 'preserve-this-media', 'The restored file contents must remain unchanged.');

$blockedDestination = $destinationDirectory . '/blocked.bin';
$missingSource = $root . '/missing-parent/blocked.bin';
file_put_contents($blockedDestination, 'never-delete-this-file');

$failures = DraftPublishingService::restoreMovedFiles([
    ['from' => $missingSource, 'to' => $blockedDestination],
]);

expect(count($failures) === 1, 'A failed restore must be reported.');
expect($failures[0]['reason'] === 'restore_rename_failed', 'The failure reason should identify the failed restore.');
expect(file_get_contents($blockedDestination) === 'never-delete-this-file', 'A failed rollback must retain the destination file.');

unlink($source);
unlink($blockedDestination);
rmdir($sourceDirectory);
rmdir($destinationDirectory);
rmdir($root);

echo "Draft publishing media rollback tests passed." . PHP_EOL;
