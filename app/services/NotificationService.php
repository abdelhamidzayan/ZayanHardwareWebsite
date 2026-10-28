<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Issue;
use App\Models\Notification;

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

        $eventKey = hash('sha256', implode('|', [
            $recipientId, $actorId ?? 0, $issueId ?? 0, $replyId ?? 0, $type, $eventIdentity,
        ]));
        $created = Notification::create(
            $recipientId, $actorId, $issueId, $replyId,
            $type, mb_substr($title, 0, 180), mb_substr($body, 0, 500),
            mb_substr($targetPath, 0, 255), $eventKey
        );
        if ($created) WebPushService::sendToUser($recipientId, $title, $body, $targetPath);
    }

    public static function notifyIssueParticipants(
        int $issueId,
        int $actorId,
        string $type,
        string $title,
        string $body,
        string $eventIdentity
    ): void {
        foreach (Issue::participantIds($issueId) as $recipientId) {
            self::notify(
                $recipientId, $actorId, $issueId, null, $type, $title, $body,
                '/issues/' . $issueId, $eventIdentity . ':' . $recipientId
            );
        }
    }
}
