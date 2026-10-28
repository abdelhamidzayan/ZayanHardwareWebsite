<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\PushSubscription;
use App\Services\AuthService;

final class PushController extends Controller
{
    public function publicKey(): Response
    {
        $key = (string) App::env('VAPID_PUBLIC_KEY', '');
        if ($key === '') return $this->json(['error' => 'push_not_configured'], 503);
        return $this->json(['publicKey' => $key]);
    }

    public function subscribe(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->json(['error' => 'csrf'], 419);
        $user = AuthService::user();
        if (!$user) return $this->json(['error' => 'unauthorized'], 401);

        $endpoint = $this->string('endpoint');
        $publicKey = $this->string('p256dh');
        $authToken = $this->string('auth');
        $parts = parse_url($endpoint);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $knownPushHost = in_array($host, ['fcm.googleapis.com', 'web.push.apple.com'], true)
            || str_ends_with($host, '.push.services.mozilla.com');
        if (strlen($endpoint) > 2048 || !is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || !$knownPushHost || isset($parts['user']) || isset($parts['pass'])
            || !preg_match('/^[A-Za-z0-9_-]{20,200}$/', $publicKey)
            || !preg_match('/^[A-Za-z0-9_-]{16,200}$/', $authToken)) {
            return $this->json(['error' => 'invalid_subscription'], 422);
        }

        PushSubscription::save(
            (int) $user['id'], $endpoint, $publicKey, $authToken,
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
        );
        return $this->json(['ok' => true], 201);
    }

    public function unsubscribe(): Response
    {
        if (!Csrf::verify($this->request->input('_token'))) return $this->json(['error' => 'csrf'], 419);
        $user = AuthService::user();
        if (!$user) return $this->json(['error' => 'unauthorized'], 401);
        $endpoint = $this->string('endpoint');
        if ($endpoint !== '' && strlen($endpoint) <= 2048) {
            PushSubscription::deleteForUserByEndpoint((int) $user['id'], $endpoint);
        }
        return $this->json(['ok' => true]);
    }

    private function string(string $key): string
    {
        $value = $this->request->input($key);
        return is_string($value) ? trim($value) : '';
    }

    private function json(array $data, int $status = 200): Response
    {
        return new Response(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']
        );
    }
}
