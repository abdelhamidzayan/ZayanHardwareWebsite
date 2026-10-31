<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/app/controllers/AuthController.php');
if (!is_string($source)) {
    fwrite(STDERR, "Unable to read AuthController.\n");
    exit(1);
}

$genericMessage = 'بيانات الدخول غير صحيحة أو الحساب غير متاح.';
$requiredEvents = [
    "App::security('auth.login.failed'",
    "App::security('auth.login.blocked_account'",
];

foreach ($requiredEvents as $event) {
    if (!str_contains($source, $event)) {
        fwrite(STDERR, 'Missing internal login audit event: ' . $event . "\n");
        exit(1);
    }
}

$invalidPosition = strpos($source, 'if ($result[\'error\'] === \'invalid\')');
$blockedPosition = strpos($source, 'if ($result[\'error\'] === \'blocked\')');
if ($invalidPosition === false || $blockedPosition === false || $blockedPosition <= $invalidPosition) {
    fwrite(STDERR, "Unable to locate both invalid and blocked login result branches.\n");
    exit(1);
}

$invalidBlock = substr($source, $invalidPosition, $blockedPosition - $invalidPosition);
$blockedEnd = strpos($source, '$user = $result[\'user\'];', $blockedPosition);
$blockedBlock = substr(
    $source,
    $blockedPosition,
    $blockedEnd === false ? null : $blockedEnd - $blockedPosition
);

if (!str_contains($invalidBlock, $genericMessage) || !str_contains($blockedBlock, $genericMessage)) {
    fwrite(STDERR, "Invalid and blocked login attempts must expose the same generic message.\n");
    exit(1);
}
if (str_contains($blockedBlock, 'الحساب موقوف') || str_contains($blockedBlock, 'account_status')) {
    fwrite(STDERR, "Blocked login response exposes account status.\n");
    exit(1);
}

echo "Login information-disclosure regression passed.\n";
