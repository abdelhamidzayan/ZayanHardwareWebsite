# PWA Push setup

1. Install the PHP dependencies from the project root:

   `composer install`

   If there is no lock file yet, resolve the dependency once with `composer update minishlink/web-push` and commit the resulting lock file.

2. Generate a VAPID key pair locally after Composer dependencies are installed:

   ```bash
   php -r 'require "vendor/autoload.php"; print_r(\Minishlink\WebPush\VAPID::createVapidKeys());'
   ```

3. Put the generated public and private keys in the server's local `.env` file. Set `VAPID_SUBJECT` to a real mailto address or site URL. Never commit the private key.

   ```dotenv
   VAPID_PUBLIC_KEY=your_public_key
   VAPID_PRIVATE_KEY=your_private_key
   VAPID_SUBJECT=mailto:your-real-admin-address@example.com
   ```

4. Serve the site over HTTPS outside localhost. Browser Push permission and service-worker registration require a secure context.

5. Open **الإشعارات** on a supported browser/device and select **تفعيل إشعارات Push**. Permission must be granted on each device separately.

6. To disable Push on a device, use the button on the same page. This removes the server subscription and unsubscribes that browser device.

The service worker caches only static assets; it must not cache notification API responses, private pages, or user data. Expired push subscriptions are removed after the push service reports them as expired.
