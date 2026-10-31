<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'app/controllers/AdminUserController.php' => [
        'admin status changes' => 'auth_version = auth_version + 1',
        'admin role changes' => 'SET role = ?, auth_version = auth_version + 1',
        'admin password resets' => 'SET password_hash = ?, auth_version = auth_version + 1',
    ],
    'app/controllers/AccountController.php' => [
        'member deletion requests' => "account_status = 'deletion_pending', auth_version = auth_version + 1",
    ],
    'app/services/AccountRetentionService.php' => [
        'account anonymization' => 'auth_version = auth_version + 1',
        'fail-closed auth_version schema check' => "'role', 'account_status', 'auth_version',",
    ],
];

$failures = [];
foreach ($checks as $relativePath => $markers) {
    $path = $root . '/' . $relativePath;
    $source = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($source)) {
        $failures[] = $relativePath . ' is missing or unreadable';
        continue;
    }

    foreach ($markers as $label => $marker) {
        if (!str_contains($source, $marker)) {
            $failures[] = $relativePath . ' does not revoke sessions for ' . $label;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Account session-revocation policy failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Account session-revocation policy passed for status, role, password reset, deletion request and anonymization.\n";
