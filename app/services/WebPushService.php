<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class WebPushService
{
    public static function sendToUser(int $userId, string $title, string $body, string $path): void
    {
        $publicKey = (string) App::env('VAPID_PUBLIC_KEY', '');
        $privateKey = (string) App::env('VAPID_PRIVATE_KEY', '');
        $subject = (string) App::env('VAPID_SUBJECT', '');
        if (!class_exists(WebPush::class)) {
            App::log('warning', 'Web Push is unavailable: run composer install on the server.');
            return;
        }
        if ($publicKey === '' || $privateKey === '' || $subject === '') {
            App::log('warning', 'Web Push is not configured: VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY and VAPID_SUBJECT are required.');
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => ['subject' => $subject, 'publicKey' => $publicKey, 'privateKey' => $privateKey],
            ]);
            $payload = json_encode([
                'title' => mb_substr($title, 0, 120),
                'body' => mb_substr($body, 0, 240),
                'url' => str_starts_with($path, '/') && !str_starts_with($path, '//') ? $path : '/notifications',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payload)) return;

            foreach (PushSubscription::forUser($userId) as $row) {
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $row['endpoint'],
                        'publicKey' => $row['public_key'],
                        'authToken' => $row['auth_token'],
                        'contentEncoding' => $row['content_encoding'] ?: 'aes128gcm',
                    ]);
                    $report = $webPush->sendOneNotification($subscription, $payload);
                    if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                        PushSubscription::deleteById((int) $row['id']);
                    }
                } catch (\Throwable $e) {
                    App::logException('WARNING', 'push.delivery_failed', $e, [
                        'user_id' => $userId,
                        'subscription_id' => (int) $row['id'],
                    ]);
                }
            }
        } catch (\Throwable $e) {
            App::logException('WARNING', 'push.initialization_failed', $e, ['user_id' => $userId]);
        }
    }
}
