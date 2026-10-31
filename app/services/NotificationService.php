<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Issue;
use App\Core\App;
use App\Models\Notification;
use Throwable;

final class NotificationService
{
    public static function notify(
        int $recipientId,
        ?int $actorId,
        ?int $issueId,
        ?int $replyId,
        string $type,
        string $title,
        string $body,
        string $targetPath,
        string $eventIdentity
    ): void {
        if ($recipientId < 1 || $recipientId === $actorId) return;
        if ($targetPath === '' || $targetPath[0] !== '/' || str_starts_with($targetPath, '//')) {
            $targetPath = '/notifications';
        }

        $body = preg_replace('/\\[\\[image:[A-Za-z0-9_-]{1,80}\\]\\]/', '[صورة مرفقة]', $body) ?? $body;
        $eventKey = hash('sha256', implode('|', [
            $recipientId, $actorId ?? 0, $issueId ?? 0, $replyId ?? 0, $type, $eventIdentity,
        ]));
        try {
            $created = Notification::create(
                $recipientId, $actorId, $issueId, $replyId,
                $type, mb_substr($title, 0, 180), mb_substr($body, 0, 500),
                mb_substr($targetPath, 0, 255), $eventKey
            );
            if ($created) WebPushService::sendToUser($recipientId, $title, $body, $targetPath);
        } catch (Throwable $error) {
            // Notifications and push are best-effort side effects. They must not
            // make a committed post/reply/diagnostic write look like it failed.
            App::logException('WARNING', 'notification.delivery_failed', $error, [
                'recipient_id' => $recipientId,
                'actor_id' => $actorId,
                'issue_id' => $issueId,
                'reply_id' => $replyId,
                'notification_type' => $type,
            ]);
        }
    }

    public static function notifyIssueParticipants(
        int $issueId,
        int $actorId,
        string $type,
        string $title,
        string $body,
        string $eventIdentity
    ): void {
        try {
            $recipientIds = Issue::participantIds($issueId);
        } catch (Throwable $error) {
            App::logException('WARNING', 'notification.participant_lookup_failed', $error, [
                'issue_id' => $issueId,
                'actor_id' => $actorId,
                'notification_type' => $type,
            ]);
            return;
        }

        foreach ($recipientIds as $recipientId) {
            self::notify(
                $recipientId, $actorId, $issueId, null, $type, $title, $body,
                '/issues/' . $issueId, $eventIdentity . ':' . $recipientId
            );
        }
    }
}
