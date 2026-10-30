# PWA Push setup

Push notifications need the Composer Web Push package, a VAPID key pair, and a real VAPID subject. The private key must stay in the server's ignored `.env` file and must never be committed to GitHub.

## 1. Install PHP dependencies

From the project root, run:

```bash
composer install
```

This project requires `minishlink/web-push`. The repository currently has no committed `composer.lock`, so the first install resolves compatible versions and creates a local lock file.

## 2. Generate the VAPID key pair

After Composer finishes:

```bash
php -r 'require "vendor/autoload.php"; $keys = \\Minishlink\\WebPush\\VAPID::createVapidKeys(); echo "VAPID_PUBLIC_KEY=" . $keys["publicKey"] . PHP_EOL; echo "VAPID_PRIVATE_KEY=" . $keys["privateKey"] . PHP_EOL;'
```

Copy both values exactly. The public key is safe to return to browsers; the private key is secret.

## 3. Add values to the server's local .env

Edit `.env` in the project root (it is ignored by Git):

```dotenv
VAPID_PUBLIC_KEY=PASTE_GENERATED_PUBLIC_KEY
VAPID_PRIVATE_KEY=PASTE_GENERATED_PRIVATE_KEY
VAPID_SUBJECT=mailto:YOUR_REAL_ADMIN_EMAIL
```

Replace the placeholders. Use an email address you control, or a valid HTTPS site URL, for `VAPID_SUBJECT`. Do not put these values in `.env.example`, source code, or GitHub commits.

## 4. Check the deployment requirements

- Use HTTPS on the live website. Browsers allow the secure-context exception for `localhost` during local development.
- Make sure the service worker is registered and migration `database/migrations/006_notifications_and_push_subscriptions.sql` has been applied.
- Install dependencies on every server deployment; the `vendor/` directory is not tracked by Git.
- Browser notification permission and Push subscription are per browser/device. Enable Push separately on each device.

## 5. Enable or disable a device

Open **الإشعارات** and choose **تفعيل إشعارات Push**. To stop Push on that device, choose **إيقاف إشعارات هذا الجهاز**. The in-app notification list continues to work independently of browser Push.

The service worker caches only static assets. It must not cache notification API responses, private pages, or user data. Expired subscriptions are removed when the push service reports them as expired.
