<?php
declare(strict_types=1);

/**
 * Focused regression test for the performance work:
 *  - App\Core\Assets builds cache-busted (?v=<mtime>) URLs for real public
 *    files, never resolves traversal/missing files, and keeps URLs stable.
 *  - AvatarController::avatarFileResponse sends ETag/Last-Modified validators
 *    and answers conditional requests with a 304 instead of the image body.
 */

use App\Core\App;
use App\Core\Assets;
use App\Core\Request;
use App\Core\Response;

$projectRoot = dirname(__DIR__, 2);
require $projectRoot . '/app/core/App.php';
require $projectRoot . '/app/core/Request.php';
require $projectRoot . '/app/core/Response.php';
require $projectRoot . '/app/core/Controller.php';
require $projectRoot . '/app/core/Assets.php';
require $projectRoot . '/app/controllers/AvatarController.php';

$fail = static function (string $message): void {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

// ---------------------------------------------------------------------------
// Assets::url
// ---------------------------------------------------------------------------
$tempRoot = sys_get_temp_dir() . '/zayan-asset-version-' . bin2hex(random_bytes(6));
if (!mkdir($tempRoot . '/config', 0700, true) || !mkdir($tempRoot . '/public/css', 0700, true)) {
    $fail('Unable to create an isolated asset test directory.');
}
copy($projectRoot . '/config/config.php', $tempRoot . '/config/config.php');
copy($projectRoot . '/config/database.php', $tempRoot . '/config/database.php');
file_put_contents($tempRoot . '/public/css/app.css', 'body{color:#000}');

App::boot($tempRoot);

$resetVersionCache = static function (): void {
    $property = new ReflectionProperty(Assets::class, 'versions');
    $property->setAccessible(true);
    $property->setValue(null, []);
};

$expectedVersion = dechex((int) filemtime($tempRoot . '/public/css/app.css'));
$resetVersionCache();
$url = Assets::url('', '/css/app.css');
if ($url !== '/css/app.css?v=' . $expectedVersion) {
    $fail('Existing public asset must receive its modification-time version. Got: ' . $url);
}

$withBase = Assets::url('/zayan/public', '/css/app.css');
if (!str_starts_with($withBase, '/zayan/public/css/app.css?v=')) {
    $fail('The base URL must be preserved in versioned asset URLs. Got: ' . $withBase);
}

$missing = Assets::url('', '/css/does-not-exist.css');
if ($missing !== '/css/does-not-exist.css') {
    $fail('Missing assets must keep a plain, working URL. Got: ' . $missing);
}

$traversal = Assets::url('', '/../config/config.php');
if (str_contains($traversal, '?v=')) {
    $fail('Path traversal attempts must never receive a version marker.');
}

// A changed file must produce a changed URL after the per-request cache resets.
touch($tempRoot . '/public/css/app.css', time() + 5);
clearstatcache(true, $tempRoot . '/public/css/app.css');
$resetVersionCache();
$updated = Assets::url('', '/css/app.css');
if ($updated === $url) {
    $fail('A modified asset must produce a new versioned URL.');
}

// ---------------------------------------------------------------------------
// Avatar conditional responses
// ---------------------------------------------------------------------------
$avatarFile = $tempRoot . '/avatar-test.webp';
file_put_contents($avatarFile, str_repeat('a', 128));

$controller = new \App\Controllers\AvatarController(new Request('GET', '/avatar/1', [], []));
$method = new ReflectionMethod($controller, 'avatarFileResponse');
$method->setAccessible(true);

$readProperty = static function (object $object, string $name): mixed {
    $property = new ReflectionProperty($object, $name);
    $property->setAccessible(true);
    return $property->getValue($object);
};

/** @var Response $fresh */
$fresh = $method->invoke($controller, $avatarFile, 'image/webp');
$freshHeaders = $readProperty($fresh, 'headers');
if ($readProperty($fresh, 'status') !== 200) {
    $fail('A first avatar request must return 200.');
}
if (($freshHeaders['Content-Length'] ?? '') !== '128') {
    $fail('A 200 avatar response must carry the full Content-Length.');
}
$etag = $freshHeaders['ETag'] ?? '';
if ($etag === '' || !isset($freshHeaders['Last-Modified'])) {
    $fail('Avatar responses must carry ETag and Last-Modified validators.');
}

foreach (['exact' => $etag, 'weak' => 'W/' . $etag, 'star' => '*'] as $case => $header) {
    $_SERVER['HTTP_IF_NONE_MATCH'] = $header;
    /** @var Response $conditional */
    $conditional = $method->invoke($controller, $avatarFile, 'image/webp');
    if ($readProperty($conditional, 'status') !== 304) {
        $fail("A matching If-None-Match ({$case}) must be answered with 304.");
    }
    if ($readProperty($conditional, 'body') !== '') {
        $fail('A 304 avatar response must not carry a body.');
    }
}

$_SERVER['HTTP_IF_NONE_MATCH'] = '"stale-etag"';
/** @var Response $stale */
$stale = $method->invoke($controller, $avatarFile, 'image/webp');
if ($readProperty($stale, 'status') !== 200) {
    $fail('A stale If-None-Match must receive the fresh 200 image.');
}
unset($_SERVER['HTTP_IF_NONE_MATCH']);

echo "Asset versioning and avatar conditional responses behave correctly.\n";

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
