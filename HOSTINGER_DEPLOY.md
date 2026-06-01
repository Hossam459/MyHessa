# Hostinger Deployment

This app is Laravel 12 and requires PHP 8.2 or newer.

## Recommended Structure

Keep the Laravel project outside the public web root and point the domain to the `public` directory:

```text
/home/USER/domains/DOMAIN/
  myhessa/
    app/
    bootstrap/
    config/
    public/
    routes/
    storage/
    vendor/
    .env
```

If Hostinger lets you change the domain document root, set it to:

```text
/home/USER/domains/DOMAIN/myhessa/public
```

If your plan only serves `public_html`, use Hostinger's shared-hosting fallback: deploy the full Laravel project into `public_html` and place this `.htaccess` in `public_html`:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

## First Deploy

1. In hPanel, set PHP to 8.2 or newer and enable the required extensions for Laravel.
2. Create a MySQL database in hPanel.
3. Upload the project with Git, SFTP, or File Manager.
4. Create `.env` on the server from `.env.example`.
5. Update production values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
FRONTEND_URL=https://your-frontend-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_hostinger_database
DB_USERNAME=your_hostinger_user
DB_PASSWORD=your_hostinger_password

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

6. Upload Firebase service credentials to `storage/firebase-credentials.json` if push notifications are used.
7. Run these commands over SSH from the project directory:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan jwt:secret --force
php artisan migrate --force
php artisan storage:link
php artisan optimize:clear
php artisan optimize
```

8. Build frontend assets locally or on the server:

```bash
npm install
npm run build
```

If building locally, upload the generated `public/build` folder.

## Cron And Queues

Add this Hostinger cron job, replacing the path:

```bash
/usr/bin/php /home/USER/domains/DOMAIN/myhessa/artisan schedule:run
```

This project uses database queues. If Hostinger does not provide a long-running process manager, add a cron job that runs every minute:

```bash
/usr/bin/php /home/USER/domains/DOMAIN/myhessa/artisan queue:work --stop-when-empty --tries=3
```

## After Each Deploy

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
```
