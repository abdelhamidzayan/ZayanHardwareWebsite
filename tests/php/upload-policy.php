<?php
declare(strict_types=1);

use App\Services\ContentEditorService;
use App\Services\HelpRequestMediaService;
use App\Services\ImageUploadPolicy;

$root = dirname(__DIR__, 2);
require $root . '/app/services/ImageUploadPolicy.php';
require $root . '/app/services/ContentEditorService.php';
require $root . '/app/services/HelpRequestMediaService.php';

function expectUploadPolicy(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

/** Create a tiny PNG fixture and alter only its declared dimensions. */
function writePngHeaderFixture(string $path, int $width, int $height): void
{
    $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p7sAAAAASUVORK5CYII=';
    $png = base64_decode($base64, true);
    if (!is_string($png) || strlen($png) < 33) {
        throw new RuntimeException('Unable to create PNG policy fixture.');
    }

    $png = substr_replace($png, pack('N', $width), 16, 4);
    $png = substr_replace($png, pack('N', $height), 20, 4);
    $crc = hash('crc32b', substr($png, 12, 17));
    $png = substr_replace($png, pack('H*', $crc), 29, 4);

    if (file_put_contents($path, $png) === false) {
        throw new RuntimeException('Unable to write PNG policy fixture.');
    }
}

$temp = sys_get_temp_dir() . '/zayan-upload-policy-' . bin2hex(random_bytes(6));
if (!mkdir($temp, 0700, true)) {
    fwrite(STDERR, "Unable to create isolated upload policy fixtures.\n");
    exit(1);
}

$valid = $temp . '/valid.png';
$wide = $temp . '/wide.png';
$manyPixels = $temp . '/many-pixels.png';
$malformed = $temp . '/malformed.bin';

try {
    writePngHeaderFixture($valid, 1600, 1200);
    writePngHeaderFixture($wide, 8001, 100);
    writePngHeaderFixture($manyPixels, 5000, 5000);
    file_put_contents($malformed, 'not an image');

    $validInfo = ImageUploadPolicy::inspect($valid);
    expectUploadPolicy($validInfo['width'] === 1600, 'Valid image width was not read.');
    expectUploadPolicy($validInfo['height'] === 1200, 'Valid image height was not read.');
    expectUploadPolicy($validInfo['pixels'] === 1_920_000, 'Valid image pixel count is incorrect.');

    $wideRejected = false;
    try {
        ImageUploadPolicy::inspect($wide);
    } catch (DomainException $error) {
        $wideRejected = $error->getMessage() === 'image_dimensions_invalid';
    }
    expectUploadPolicy($wideRejected, 'Image wider than the per-dimension cap must be rejected.');

    $pixelRejected = false;
    try {
        ImageUploadPolicy::inspect($manyPixels);
    } catch (DomainException $error) {
        $pixelRejected = $error->getMessage() === 'image_dimensions_invalid';
    }
    expectUploadPolicy($pixelRejected, 'Image exceeding the total pixel cap must be rejected.');

    $invalidRejected = false;
    try {
        ImageUploadPolicy::inspect($malformed);
    } catch (DomainException $error) {
        $invalidRejected = $error->getMessage() === 'image_invalid';
    }
    expectUploadPolicy($invalidRejected, 'Malformed image data must be rejected.');

    $issueStructureRejected = false;
    try {
        ContentEditorService::validateUploads(['name' => []], []);
    } catch (DomainException $error) {
        $issueStructureRejected = $error->getMessage() === 'invalid_upload_structure';
    }
    expectUploadPolicy($issueStructureRejected, 'Issue media validator must reject malformed upload arrays.');

    $helpStructureRejected = false;
    try {
        HelpRequestMediaService::validate(['name' => 'not-an-array']);
    } catch (DomainException $error) {
        $helpStructureRejected = $error->getMessage() === 'invalid_attachment_structure';
    }
    expectUploadPolicy($helpStructureRejected, 'Personal-help media validator must reject malformed upload arrays.');

    $countMismatchRejected = false;
    try {
        HelpRequestMediaService::validate([
            'name' => ['one.jpg', 'two.jpg'],
            'error' => [UPLOAD_ERR_NO_FILE],
            'tmp_name' => ['', ''],
            'size' => [0, 0],
        ]);
    } catch (DomainException $error) {
        $countMismatchRejected = $error->getMessage() === 'invalid_attachment_structure';
    }
    expectUploadPolicy($countMismatchRejected, 'Personal-help media validator must reject mismatched upload array lengths.');

    $issueIndexMismatchRejected = false;
    try {
        ContentEditorService::validateUploads([
            'name' => [0 => 'fixture.jpg'],
            'error' => [1 => UPLOAD_ERR_NO_FILE],
            'tmp_name' => [0 => ''],
            'size' => [0 => 0],
        ], []);
    } catch (DomainException $error) {
        $issueIndexMismatchRejected = $error->getMessage() === 'invalid_upload_structure';
    }
    expectUploadPolicy($issueIndexMismatchRejected, 'Issue media validator must reject mismatched upload field indexes.');

    $helpIndexMismatchRejected = false;
    try {
        HelpRequestMediaService::validate([
            'name' => [0 => 'fixture.jpg'],
            'error' => [1 => UPLOAD_ERR_NO_FILE],
            'tmp_name' => [0 => ''],
            'size' => [0 => 0],
        ]);
    } catch (DomainException $error) {
        $helpIndexMismatchRejected = $error->getMessage() === 'invalid_attachment_structure';
    }
    expectUploadPolicy($helpIndexMismatchRejected, 'Personal-help media validator must reject mismatched upload field indexes.');

    echo "Image pixel limits and malformed upload structure tests passed.\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Upload policy test failed: " . $error::class . ' ' . $error->getMessage() . PHP_EOL);
    exit(1);
} finally {
    foreach ([$valid, $wide, $manyPixels, $malformed] as $file) {
        if (is_file($file)) unlink($file);
    }
    rmdir($temp);
}
