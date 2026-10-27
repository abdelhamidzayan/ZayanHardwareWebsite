# Zayan Hardware (Hardware Tech)

PHP 8.2+ MVC + MySQL. Document root must be `public/`.

## Run locally
1. Copy `.env.example` to `.env` and edit it.
2. (Optional) `composer install` — without it `public/index.php` uses a built-in autoloader.
3. Serve `public/`:
   - Quick: `php -S 127.0.0.1:8080 -t public`
   - XAMPP: open `http://localhost/zayan-hardware/public/`
4. Open `/feed`. Unknown URLs must show the 404 page.

Logs: `storage/logs/app.log`.
