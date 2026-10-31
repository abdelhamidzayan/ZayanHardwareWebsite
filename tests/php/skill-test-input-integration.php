<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Services\SkillTestService;

if (PHP_SAPI !== 'cli' || (string) getenv('DB_NAME') !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Skill test input integration is restricted to zayan_fresh_install_test.\n");
    exit(2);
}

$root = dirname(__DIR__, 2);
require $root . '/app/core/App.php';
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $map = [
            'App\\Core\\' => '/app/core/',
            'App\\Controllers\\' => '/app/controllers/',
            'App\\Models\\' => '/app/models/',
            'App\\Services\\' => '/app/services/',
            'App\\Middleware\\' => '/app/middleware/',
        ];
        foreach ($map as $prefix => $directory) {
            if (!str_starts_with($class, $prefix)) continue;
            $file = $root . $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require $file;
            return;
        }
    });
}

App::boot($root);
$pdo = Database::connection();
$ordinaryUserId = 3;
$user = $pdo->prepare("SELECT id FROM users WHERE id = ? AND account_status = 'active' AND role <> 'admin' LIMIT 1");
$user->execute([$ordinaryUserId]);
if (!$user->fetchColumn()) {
    fwrite(STDERR, "Skill test fixture requires active non-admin user id 3.\n");
    exit(2);
}

$domainId = (int) $pdo->query(
    'SELECT id FROM skill_domains WHERE is_active = 1 ORDER BY id LIMIT 1'
)->fetchColumn();
if ($domainId < 1) {
    fwrite(STDERR, "Skill test fixture requires an active skill domain.\n");
    exit(2);
}

$questions = [
    [
        'question' => 'Which rail is being measured?',
        'options' => ['a' => 'Battery', 'b' => 'VDD_MAIN'],
        'correct' => 'b',
        'internal_marker' => 'must-not-leak',
        'answer' => 'also-private',
    ],
    [
        'question' => 'Which measurement comes next?',
        'options' => ['a' => 'Resistance', 'b' => 'Replace CPU'],
        'correct' => 'b',
    ],
];
$pdo->prepare(
    'INSERT INTO skill_tests
        (skill_domain_id, title, version, questions_json, pass_score, max_attempts, cooldown_minutes, is_active, created_by)
     VALUES (?, ?, ?, ?, 100, 1, 1, 1, 1)'
)->execute([
    $domainId,
    'CI answer bounds ' . strtoupper(bin2hex(random_bytes(4))),
    'ci-' . bin2hex(random_bytes(3)),
    json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
]);
$testId = (int) $pdo->lastInsertId();

$publicTest = SkillTestService::findActive($testId);
if (!is_array($publicTest)
    || isset($publicTest['questions'][0]['correct'])
    || isset($publicTest['questions'][0]['answer'])
    || isset($publicTest['questions'][0]['explanation'])
    || isset($publicTest['questions'][0]['internal_marker'])) {
    fwrite(STDERR, "Public skill-test data must not expose answer keys or explanations.\\n");
    exit(1);
}

$malformedQuestions = ['first-question' => $questions[0]];
$pdo->prepare(
    'INSERT INTO skill_tests
        (skill_domain_id, title, version, questions_json, pass_score, max_attempts, cooldown_minutes, is_active, created_by)
     VALUES (?, ?, ?, ?, 70, 1, 1, 1, 1)'
)->execute([
    $domainId,
    'CI malformed list ' . strtoupper(bin2hex(random_bytes(4))),
    'ci-' . bin2hex(random_bytes(3)),
    json_encode($malformedQuestions, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
]);
$malformedTestId = (int) $pdo->lastInsertId();
if (SkillTestService::findActive($malformedTestId) !== null) {
    fwrite(STDERR, "Malformed associative question collections must not be exposed as active tests.\\n");
    exit(1);
}

$largeInvalidAnswer = str_repeat('X', 10000);
$result = SkillTestService::submit($ordinaryUserId, $testId, [
    '0' => $largeInvalidAnswer,
    '1' => 'b',
]);
if ($result['passed'] !== false || abs((float) $result['score'] - 50.0) > 0.01) {
    fwrite(STDERR, "Only valid option keys should contribute to the skill test score.\n");
    exit(1);
}

$attemptStmt = $pdo->prepare(
    'SELECT answers_json, score, passed FROM skill_test_attempts
     WHERE user_id = ? AND skill_test_id = ? LIMIT 1'
);
$attemptStmt->execute([$ordinaryUserId, $testId]);
$attempt = $attemptStmt->fetch(PDO::FETCH_ASSOC);
$storedAnswers = is_array($attempt) ? json_decode((string) $attempt['answers_json'], true) : null;
if (!is_array($storedAnswers)
    || array_values($storedAnswers) !== ['', 'b']
    || strlen((string) $attempt['answers_json']) > 200
    || (int) $attempt['passed'] !== 0) {
    fwrite(STDERR, "Attempt history must store only bounded valid option keys; invalid/oversized values must become empty.\n");
    exit(1);
}

$pdo->prepare('UPDATE skill_domains SET is_active = 0 WHERE id = ?')->execute([$domainId]);
try {
    if (SkillTestService::findActive($testId) !== null) {
        throw new RuntimeException('Active skill tests must be hidden when their domain is inactive.');
    }
    $inactiveDomainRejected = false;
    try {
        SkillTestService::submit($ordinaryUserId, $testId, ['0' => 'b', '1' => 'b']);
    } catch (DomainException $error) {
        $inactiveDomainRejected = $error->getMessage() === 'test_unavailable';
    }
    if (!$inactiveDomainRejected) {
        throw new RuntimeException('Skill-test submission must be rejected when the skill domain is inactive.');
    }
} finally {
    $pdo->prepare('UPDATE skill_domains SET is_active = 1 WHERE id = ?')->execute([$domainId]);
}

echo "Skill test input integration passed: unknown and oversized answers are not persisted.\n";
