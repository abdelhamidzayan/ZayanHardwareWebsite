<?php
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\AccountController;
use App\Controllers\AvatarController;
use App\Controllers\HelpRequestController;
use App\Controllers\AdminUserController;
use App\Controllers\AdminFeaturedPostsController;
use App\Controllers\AdminUpdateController;
use App\Controllers\AdminSkillTestController;
use App\Controllers\FeedController;
use App\Controllers\HomeController;
use App\Controllers\IssueController;
use App\Controllers\NotificationController;
use App\Controllers\PushController;
use App\Controllers\WorkbenchController;
use App\Controllers\MyPostsController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;
use App\Controllers\SkillTestController;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    $router->get('/help', [PageController::class, 'help']);
    $router->get('/policies', [PageController::class, 'policies']);
    $router->get('/settings', [PageController::class, 'settings']);
    $router->post('/settings/help-availability', [PageController::class, 'saveHelpAvailability']);
    $router->get('/feed', [FeedController::class, 'index']);
    $router->get('/workbench', [WorkbenchController::class, 'index']);
    $router->get('/my-posts', [MyPostsController::class, 'index']);
    $router->get('/issues/create', [IssueController::class, 'create']);
    $router->post('/issues/create', [IssueController::class, 'create']);

    $router->get('/issues/{id}', [IssueController::class, 'show']);
    $router->get('/attachments/{id}', [IssueController::class, 'attachment']);
    $router->post('/issues/{id}/reply', [IssueController::class, 'reply']);
    $router->post('/issues/{id}/replies/{replyId}/edit', [IssueController::class, 'editReply']);
    $router->post('/issues/{id}/replies/{replyId}/delete', [IssueController::class, 'deleteReply']);
    $router->post('/issues/{id}/diagnostic-updates', [IssueController::class, 'diagnosticUpdate']);
    $router->post('/issues/{id}/diagnostic-updates/{updateId}/edit', [IssueController::class, 'editDiagnosticUpdate']);
    $router->post('/issues/{id}/diagnostic-updates/{updateId}/delete', [IssueController::class, 'deleteDiagnosticUpdate']);
    $router->post('/issues/{id}/confirm-repair', [IssueController::class, 'confirmRepair']);
    $router->post('/issues/{id}/reject-repair', [IssueController::class, 'rejectRepairConfirmation']);
    $router->post('/issues/{id}/edit', [IssueController::class, 'edit']);
    $router->post('/issues/{id}/delete', [IssueController::class, 'deleteIssue']);
    $router->post('/issues/{id}/vote', [IssueController::class, 'vote']);
    $router->post('/issues/{id}/accept', [IssueController::class, 'accept']);
    $router->post('/issues/{id}/close', [IssueController::class, 'close']);
    $router->post('/issues/{id}/reopen', [IssueController::class, 'reopen']);
    $router->post('/issues/{id}/review-result', [IssueController::class, 'reviewResult']);
    $router->get('/notifications', [NotificationController::class, 'index']);
    $router->post('/notifications/read-all', [NotificationController::class, 'readAll']);
    $router->post('/notifications/{id}/open', [NotificationController::class, 'open']);
    $router->get('/push/public-key', [PushController::class, 'publicKey']);
    $router->post('/push/subscribe', [PushController::class, 'subscribe']);
    $router->post('/push/unsubscribe', [PushController::class, 'unsubscribe']);

    $router->get('/login', [AuthController::class, 'showLogin']);
    $router->post('/login', [AuthController::class, 'login']);
    $router->get('/register', [AuthController::class, 'showRegister']);
    $router->post('/register', [AuthController::class, 'register']);
    $router->get('/register/usernames', [AuthController::class, 'usernames']);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->post('/account/deletion-request', [AccountController::class, 'requestDeletion']);
    $router->get('/skills/tests', [SkillTestController::class, 'index']);
    $router->get('/admin/skill-tests', [AdminSkillTestController::class, 'index']);
    $router->post('/admin/skill-tests', [AdminSkillTestController::class, 'create']);
    $router->post('/admin/skill-tests/{id}/status', [AdminSkillTestController::class, 'setStatus']);
    $router->get('/skills/tests/{id}', [SkillTestController::class, 'show']);
    $router->post('/skills/tests/{id}/submit', [SkillTestController::class, 'submit']);
    $router->get('/admin', [AdminFeaturedPostsController::class, 'index']);
    $router->get('/admin/update', [AdminUpdateController::class, 'index']);
    $router->post('/admin/update', [AdminUpdateController::class, 'run']);
    $router->post('/admin/featured-posts/{id}', [AdminFeaturedPostsController::class, 'update']);
    $router->get('/admin/users', [AdminUserController::class, 'index']);
    $router->post('/admin/users/{id}/password-reset', [AdminUserController::class, 'resetPassword']);
    $router->post('/admin/users/{id}/status', [AdminUserController::class, 'setStatus']);
    $router->post('/admin/users/{id}/role', [AdminUserController::class, 'setRole']);

    $router->get('/profile/{username}', [ProfileController::class, 'show']);
    $router->post('/profile/avatar', [AvatarController::class, 'update']);
    $router->get('/avatar/{userId}', [AvatarController::class, 'show']);
    $router->get('/help-requests', [HelpRequestController::class, 'index']);
    $router->get('/help-requests/attachments/{id}', [HelpRequestController::class, 'attachment']);
    $router->get('/help-requests/{id}', [HelpRequestController::class, 'show']);
    $router->post('/help-requests/create', [HelpRequestController::class, 'create']);
    $router->post('/help-requests/{id}/accept', [HelpRequestController::class, 'accept']);
    $router->post('/help-requests/{id}/decline', [HelpRequestController::class, 'decline']);
    $router->post('/help-requests/{id}/messages', [HelpRequestController::class, 'send']);
    $router->post('/help-requests/{id}/close', [HelpRequestController::class, 'close']);
    $router->post('/help-requests/{id}/publication', [HelpRequestController::class, 'proposePublication']);
    $router->post('/help-publications/{id}/respond', [HelpRequestController::class, 'respondPublication']);
    $router->get('/help-library', [HelpRequestController::class, 'library']);
    $router->get('/help-library/{id}', [HelpRequestController::class, 'publicPost']);

    // Navigation targets that are not built yet.
    foreach (['/search', '/market'] as $path) {
        $router->get($path, [PageController::class, 'soon']);
    }
};
