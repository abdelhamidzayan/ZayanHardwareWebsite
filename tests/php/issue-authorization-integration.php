<?php
declare(strict_types=1);

use App\Core\App;
use App\Models\Database;
use App\Models\Issue;

if (PHP_SAPI !== 'cli' || (string) getenv('DB_NAME') !== 'zayan_fresh_install_test') {
    fwrite(STDERR, "Issue authorization integration is restricted to zayan_fresh_install_test.\n");
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
$adminId = 1;
$ordinaryUserId = 3;

$issueId = Issue::create(
    $adminId,
    'technical_post',
    'Issue authorization integration fixture',
    null,
    null,
    'Disposable content for model-level authorization and vote toggle tests.',
    ''
);
$replyId = Issue::addReply($issueId, $adminId, 'Disposable solution reply from the owner');

$unauthorizedReviewRejected = false;
$pdo->prepare(
    "INSERT INTO issue_results
        (issue_id, submitted_by, was_repaired, final_cause, repair_action, helpful_reply_id)
     VALUES (?, ?, 0, ?, NULL, NULL)"
)->execute([$issueId, $adminId, 'Disposable test result']);
$resultId = (int) $pdo->lastInsertId();

try {
    Issue::reviewResult($issueId, $resultId, $ordinaryUserId, true);
} catch (DomainException $error) {
    $unauthorizedReviewRejected = $error->getMessage() === 'reviewer_not_authorized';
}
if (!$unauthorizedReviewRejected) {
    fwrite(STDERR, "A normal active member must not review an issue result through the model.\n");
    exit(1);
}

Issue::reviewResult($issueId, $resultId, $adminId, true);
$reviewStatus = $pdo->prepare('SELECT review_status FROM issue_results WHERE id = ?');
$reviewStatus->execute([$resultId]);
if ($reviewStatus->fetchColumn() !== 'approved') {
    fwrite(STDERR, "An active administrator must be able to approve a pending result.\n");
    exit(1);
}

$ownVoteRejected = false;
try {
    Issue::vote($replyId, $adminId);
} catch (DomainException $error) {
    $ownVoteRejected = $error->getMessage() === 'invalid_vote';
}
if (!$ownVoteRejected) {
    fwrite(STDERR, "A reply author must not be able to vote for their own reply.\n");
    exit(1);
}

Issue::vote($replyId, $ordinaryUserId);
$voteCount = $pdo->prepare('SELECT COUNT(*) FROM reply_votes WHERE reply_id = ? AND user_id = ?');
$voteCount->execute([$replyId, $ordinaryUserId]);
if ((int) $voteCount->fetchColumn() !== 1) {
    fwrite(STDERR, "The first vote toggle must create exactly one vote record.\n");
    exit(1);
}

Issue::vote($replyId, $ordinaryUserId);
$voteCount->execute([$replyId, $ordinaryUserId]);
if ((int) $voteCount->fetchColumn() !== 0) {
    fwrite(STDERR, "The second vote toggle must remove the existing vote record.\n");
    exit(1);
}

echo "Issue model authorization and vote toggle integration passed.\n";
