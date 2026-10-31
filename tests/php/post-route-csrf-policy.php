<?php
declare(strict_types=1);

/**
 * Every declared POST route must enforce CSRF directly or delegate to a method
 * in the same controller that enforces it. Public POST access is separately
 * allow-listed to the login and registration forms only.
 */
$root = dirname(__DIR__, 2);
$routesPath = $root . '/config/routes.php';
$routesSource = file_get_contents($routesPath);
if (!is_string($routesSource)) {
    fwrite(STDERR, "Unable to read config/routes.php.\n");
    exit(1);
}

$routePattern = '/\$router->post(Public)?\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*\[([A-Za-z_][A-Za-z0-9_]*)::class\s*,\s*[\'"]([A-Za-z_][A-Za-z0-9_]*)[\'"]\s*\]\s*\)\s*;/';
if (preg_match_all($routePattern, $routesSource, $routeMatches, PREG_SET_ORDER) === false) {
    fwrite(STDERR, "Unable to parse POST route declarations.\n");
    exit(1);
}
if ($routeMatches === []) {
    fwrite(STDERR, "No POST routes were found; refusing to treat an empty scan as success.\n");
    exit(1);
}

$publicPostPaths = [];
$checked = [];
$failures = [];
$sourceCache = [];

$loadController = static function (string $class) use ($root, &$sourceCache): string {
    if (isset($sourceCache[$class])) return $sourceCache[$class];
    $path = $root . '/app/controllers/' . $class . '.php';
    $source = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($source)) {
        throw new RuntimeException('Controller source is missing: ' . $class);
    }
    $sourceCache[$class] = $source;
    return $source;
};

$methodSource = static function (string $source, string $method): ?string {
    $pattern = '/(?:public|protected|private)\s+function\s+' . preg_quote($method, '/') . '\s*\(/';
    if (!preg_match($pattern, $source, $match, PREG_OFFSET_CAPTURE)) return null;
    $start = $match[0][1];
    $remaining = substr($source, $start + strlen($match[0][0]));
    if (!is_string($remaining)) return null;

    if (preg_match('/\n\s{4}(?:public|protected|private)\s+function\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/', $remaining, $next, PREG_OFFSET_CAPTURE)) {
        $end = $start + strlen($match[0][0]) + $next[0][1];
        return substr($source, $start, $end - $start);
    }

    return substr($source, $start);
};

$hasCsrf = static function (string $class, string $method, array $visited = []) use (
    &$hasCsrf,
    $loadController,
    $methodSource
): bool {
    $key = $class . '::' . $method;
    if (isset($visited[$key])) return false;
    $visited[$key] = true;

    $source = $loadController($class);
    $block = $methodSource($source, $method);
    if ($block === null) {
        throw new RuntimeException('Route handler method is missing: ' . $key);
    }
    if (str_contains($block, 'Csrf::verify(')) return true;

    // Allow explicit same-controller delegation (e.g. accept/decline -> respond).
    if (preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $block, $delegates)) {
        foreach (array_unique($delegates[1]) as $delegate) {
            if ($hasCsrf($class, $delegate, $visited)) return true;
        }
    }
    return false;
};

foreach ($routeMatches as $match) {
    $isPublic = $match[1] === 'Public';
    $path = $match[2];
    $class = $match[3];
    $method = $match[4];

    if ($isPublic) $publicPostPaths[] = $path;
    $routeLabel = 'POST ' . $path . ' → ' . $class . '::' . $method;

    try {
        if (!$hasCsrf($class, $method)) {
            $failures[] = $routeLabel . ' does not verify CSRF in its handler or an explicit same-controller delegate.';
        } else {
            $checked[] = $routeLabel;
        }
    } catch (Throwable $error) {
        $failures[] = $routeLabel . ': ' . $error->getMessage();
    }
}

$expectedPublic = ['/login', '/register'];
sort($publicPostPaths);
sort($expectedPublic);
if ($publicPostPaths !== $expectedPublic) {
    $failures[] = 'Unexpected public POST routes: ' . implode(', ', $publicPostPaths);
}

if ($failures !== []) {
    fwrite(STDERR, "POST route CSRF policy failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

printf(
    "POST route CSRF policy passed: %d routes checked; %d public POST routes allow-listed.\n",
    count($checked),
    count($publicPostPaths)
);
