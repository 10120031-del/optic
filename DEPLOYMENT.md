# Deploying Lucent Optics

Everything needed to take this repository from a fresh server to a live shop,
and to push changes afterwards. Written for a single Linux host running nginx
or Apache with php-fpm — the most common shape for a Laravel shop this size.

---

## 1. What the server needs

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.2 or newer | `composer.json` floor; 8.3/8.4 also fine |
| PHP extensions | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo_mysql`, `session`, `tokenizer`, `xml` | Laravel's baseline plus MySQL |
| MySQL / MariaDB | MySQL 8.0+ or MariaDB 10.6+ | `utf8mb4` and the migrations' index widths |
| Composer | 2.x | PHP dependencies |
| Node.js + npm | Node 20 LTS or newer | Building CSS/JS; **only needed at build time** |
| Outbound HTTPS | — | `npm install` downloads the 3.7 MB face-landmarker model |

There is no Redis or Memcached requirement: sessions, cache and the queue all
run on database tables created by the `0001_01_01_*` migrations.

> **Building elsewhere?** `public/build` and `public/mediapipe` are gitignored,
> so they do not arrive with a `git clone`. Either install Node on the server,
> or run `npm ci && npm run build` in CI and ship those two directories with
> the release. A deploy that skips this serves a page with no styling and a
> face scanner that silently never finds its model.

---

## 2. First deploy

```bash
# 1 — Code
git clone <your-repo-url> /var/www/lucent-optics
cd /var/www/lucent-optics

# 2 — Environment
cp .env.production.example .env
# Fill in APP_URL, the DB_* block, the MAIL_* block and SESSION_DOMAIN,
# then generate the key ONCE (see the warning below):
php artisan key:generate

# 3 — Dependencies and assets
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build

# 4 — Database
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force

# 5 — Uploads + caches
php artisan storage:link
php artisan optimize

# 6 — The first staff account
php artisan app:create-admin
```

> **APP_KEY is generated once, ever.** It encrypts session payloads and any
> encrypted column. Re-running `key:generate` on a live site logs every
> customer out and makes previously encrypted data unreadable. Back the value
> up with your other secrets.

`app:create-admin` prompts for a name, email and password (hidden — it never
reaches your shell history). Storefront registration always creates a
*customer*, so without this command nobody can reach `/admin`.

**Never run `php artisan db:seed` without `--class=ProductionSeeder` on a live
server.** The default `DatabaseSeeder` is a development fixture that creates
`admin@example.com` with a known password and a placeholder catalogue.
`AppServiceProvider` also blocks `migrate:fresh` and `db:wipe` once
`APP_ENV=production`, so a mistyped command can't drop the shop's data.

---

## 3. Web server

The document root is **`public/`**, never the project root. Pointing it at the
project root exposes `.env`, `storage/` and every prescription scan.

### nginx

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;
    root /var/www/lucent-optics/public;

    ssl_certificate     /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    index index.php;
    charset utf-8;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Frame photos and the MediaPipe model are immutable once published.
    location ~* ^/(storage|mediapipe|build)/ {
        expires 30d;
        access_log off;
        try_files $uri =404;
    }

    # Sized for the largest upload the app accepts: a review with 6 photos at
    # 8 MB each. Prescriptions are capped at 10 MB, frame photos at 8 MB each.
    # Lower this only if you lower the validation rules to match.
    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    error_page 404 /index.php;
}

server {
    listen 80;
    server_name example.com;
    return 301 https://$host$request_uri;
}
```

### Apache

`public/.htaccess` already carries the rewrite rules, so the vhost only needs:

```apache
<VirtualHost *:443>
    ServerName example.com
    DocumentRoot /var/www/lucent-optics/public

    <Directory /var/www/lucent-optics/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Largest accepted upload: a review with 6 photos at 8 MB each.
    LimitRequestBody 67108864
</VirtualHost>
```

Apache also needs `a2enmod rewrite`, and `FollowSymLinks` must be permitted or
the `public/storage` symlink will not serve frame photos.

Whichever server you use, PHP's own limits have to match or a large upload dies
before Laravel ever validates it:

```ini
; php.ini
upload_max_filesize = 10M   ; the biggest single file (a prescription scan)
post_max_size = 64M         ; the biggest whole request (6 review photos)
max_file_uploads = 20
```

### Behind a proxy or CDN

`bootstrap/app.php` trusts all proxies (`trustProxies(at: '*')`) so Laravel
reads `X-Forwarded-Proto` and builds `https://` URLs. That is correct when only
your proxy can reach php-fpm. If the app is directly reachable from the
internet as well, pin it to your proxy's IP range instead.

---

## 4. Queue worker (required for campaign email)

`Admin\PromotionCampaignController` **queues** its mail. With no worker
running, campaigns silently pile up in the `jobs` table and no customer ever
receives one. Run one worker under supervisor:

```ini
; /etc/supervisor/conf.d/lucent-worker.conf
[program:lucent-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/lucent-optics/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/lucent-optics
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/lucent-optics/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start lucent-worker:*
```

systemd equivalent, if you prefer it:

```ini
# /etc/systemd/system/lucent-worker.service
[Unit]
Description=Lucent Optics queue worker
After=network.target mysql.service

[Service]
User=www-data
Restart=always
WorkingDirectory=/var/www/lucent-optics
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Workers hold code in memory, so every deploy must call `php artisan queue:restart`
(`deploy.sh` does). Failed jobs land in the `failed_jobs` table — check it with
`php artisan queue:failed` and retry with `php artisan queue:retry all`.

---

## 5. Scheduler (optional today)

Nothing is scheduled yet, so no cron entry is strictly required. Add this line
the moment a scheduled task appears in `routes/console.php`, and it will keep
working for every task after:

```cron
* * * * * cd /var/www/lucent-optics && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Permissions

php-fpm writes logs, compiled views, sessions and uploads; everything else can
stay read-only to it.

```bash
sudo chown -R deploy:www-data /var/www/lucent-optics
sudo find /var/www/lucent-optics -type f -exec chmod 644 {} \;
sudo find /var/www/lucent-optics -type d -exec chmod 755 {} \;
sudo chmod -R ug+rwx storage bootstrap/cache
sudo chmod 640 .env && sudo chown deploy:www-data .env
```

Run deploys as `deploy`, not as root — caches written by root are unreadable to
php-fpm and produce a blank 500 on the next request.

`storage/app/private/prescriptions/` holds uploaded medical records. It is
outside the web root by design and is only ever served through
`GET /prescriptions/{prescription}/file`, which checks that the requester owns
the record or is staff. Do not add a symlink or alias that exposes it.

---

## 7. Smoke test after deploying

```bash
php artisan about                       # env=production, debug OFF, caches CACHED
curl -sf https://example.com/up         # health endpoint → 200
```

Then, in a browser:

1. `/` — homepage renders **with styling** (proves `public/build` exists).
2. `/frames` — product photos load (proves the `storage` symlink works).
3. `/face-match` — the scanner starts (proves `public/mediapipe` was staged).
4. Register a throwaway customer, add to cart, place a `cash_on_delivery` order.
5. Sign in as staff and open a prescription's **Open file** link.
6. Visit a nonsense URL — you should get the branded 404, never a stack trace.

If step 6 shows a stack trace, `APP_DEBUG` is still `true`. Fix it and re-run
`php artisan optimize` before anything else.

---

## 8. Routine deploys

```bash
./deploy.sh --pull
```

The script refuses to run with `APP_DEBUG=true` or a missing `APP_KEY`, puts
the shop in maintenance mode behind the branded 503 page, installs, builds,
migrates, re-caches, restarts workers, and brings it back up — including if a
step fails partway.

Asset- or view-only change and you want zero downtime? `./deploy.sh --no-down`.

---

## 9. Backups

Two things are irreplaceable: the database and `storage/app`.

```bash
# Nightly, via cron
mysqldump --single-transaction --routines lucent_optics | gzip > /backups/db-$(date +\%F).sql.gz
tar czf /backups/uploads-$(date +\%F).tar.gz -C /var/www/lucent-optics storage/app
```

Also keep `.env` (or at least `APP_KEY`) in your secret store. A database
restored without the original `APP_KEY` still works, but every encrypted value
and active session is lost.

Restore:

```bash
gunzip < /backups/db-2026-08-30.sql.gz | mysql lucent_optics
tar xzf /backups/uploads-2026-08-30.tar.gz -C /var/www/lucent-optics
php artisan optimize:clear && php artisan optimize
```

---

## 10. Rolling back

```bash
php artisan down --render="errors::503" --retry=15
git checkout <previous-tag>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize:clear && php artisan optimize
php artisan queue:restart
php artisan up
```

Migrations are the part that does not roll back cleanly. If the release you are
reverting added one, restore the pre-deploy database dump rather than running
`migrate:rollback` against live data.

---

## 11. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Page loads with no CSS/JS | `public/build` missing | `npm ci && npm run build` |
| Face scanner never starts | `public/mediapipe` missing | `npm run mediapipe` (needs outbound HTTPS) |
| Frame photos 404 | storage symlink missing | `php artisan storage:link` |
| Blank white 500 | unwritable `storage/` or `bootstrap/cache` | fix ownership (§6), then `php artisan optimize:clear` |
| Route/config change has no effect | stale caches | `php artisan optimize:clear && php artisan optimize` |
| Campaign emails never arrive | no queue worker, or `MAIL_MAILER=log` | §4, and set a real mailer |
| Mixed-content warnings behind a proxy | proxy not forwarding `X-Forwarded-Proto` | fix the proxy header (§3) |
| `SQLSTATE[42S02]` on sessions/cache | migrations not run | `php artisan migrate --force` |
| Can't reach `/admin` | account is a customer | `php artisan app:create-admin` |

Logs live in `storage/logs/laravel-*.log` (daily, 14-day retention with the
production env template).

---

## 12. Before you call it live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` on `https://`
- [ ] `APP_KEY` generated once and backed up
- [ ] `SESSION_SECURE_COOKIE=true` and TLS actually enforced
- [ ] Document root is `public/`; `.env` is not fetchable over HTTP
- [ ] Database user is app-scoped, not `root`
- [ ] `MAIL_MAILER` is a real transport, `MAIL_FROM_ADDRESS` on your domain
- [ ] Queue worker running and surviving a reboot
- [ ] Nightly database + `storage/app` backups, and one restore rehearsed
- [ ] No demo accounts: `SELECT email, role FROM users WHERE email LIKE '%@example.com';` returns nothing
- [ ] Staff know the drill: cash is only recorded as collected when they set an order to Paid or Delivered in `/admin/orders`
