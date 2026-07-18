# Deployment

## Local Development (Docker Compose)

```bash
docker compose up -d --build
```

On first boot, the `app` container automatically installs Composer packages, generates `APP_KEY`, and runs migrations.

| Service | Image | Exposed as |
|---|---|---|
| `app` | `docker/php/Dockerfile` (php:8.4-fpm-alpine) | internal only (9000) |
| `nginx` | nginx:stable-alpine | http://localhost:8000 (Backend API) |
| `node` | node:22-alpine (`npm run dev -- --host`) | http://localhost:5173 (Frontend) |
| `postgres` | postgres:17-alpine | localhost:5432 |
| `redis` | redis:7-alpine | localhost:6379 |

## Common Commands

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app composer install
docker compose exec app vendor/bin/pint
docker compose exec app vendor/bin/phpstan analyse --memory-limit=512M
docker compose exec node npm run test -- --run
docker compose exec node npx vue-tsc --noEmit
docker compose exec node npx eslint .
docker compose logs -f app
```

---

## Production

This section is the source of truth for standing up DentalSuite on a real VPS for the first
paying clinic. It intentionally keeps the same bind-mount model as local development (pull code
onto the server, `docker compose up`) rather than baking an immutable image — simpler to operate
correctly for a single-server deployment, and consistent with the project's "keep it simple, do
not over-engineer" philosophy. Revisit only if/when the project needs multi-server rollouts,
zero-downtime blue/green deploys, or a real container registry — none of which are a V1 need.

### Server requirements

- A VPS (2 vCPU / 4 GB RAM minimum for a single small-to-mid clinic; scale up per real usage)
  running a recent Linux distro (Ubuntu 24.04 LTS or Debian 12 recommended).
- Docker Engine + the Docker Compose plugin installed (`docker compose`, not the legacy
  standalone `docker-compose`).
- A domain name pointed at the server's IP (A/AAAA record) — required for SSL (see below) and
  for `SANCTUM_STATEFUL_DOMAINS`/CORS to work.
- Outbound internet access for the initial `docker compose build` (pulls base images, runs
  `composer install`/`npm install`).
- A host-level firewall (e.g. `ufw`) allowing only 22 (SSH), 80, and 443 inbound — Postgres and
  Redis must **never** be reachable from outside the server (see "Docker production topology"
  below — this repo's `docker-compose.prod.yml` already doesn't publish their ports, but a host
  firewall is defense-in-depth against future misconfiguration).

### Getting the code onto the server

```bash
git clone <your-repo-url> /opt/dentalsuite
cd /opt/dentalsuite
```

Every subsequent deploy is `git pull` + rebuild (see "Deploying an update" below).

### Environment files

Two separate templates exist specifically so a production `.env` is never accidentally created
by copying the local-dev one (`backend/.env.example` ships `APP_DEBUG=true`, no secure-cookie
flags — intentional for a comfortable dev loop, dangerous in production):

```bash
cp backend/.env.production.example backend/.env
cp frontend/.env.production.example frontend/.env.production
```

Edit `backend/.env` and fill in every value marked `CHANGE-ME`:

- `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` — your real production domain(s).
- `DB_PASSWORD` — a generated 32+ char secret (`openssl rand -base64 32`), not `secret`.
- `MAIL_FROM_ADDRESS` and real `MAIL_*` transport settings once outbound email is needed
  (appointment reminders, password resets) — `MAIL_MAILER=log` is a placeholder, not a production
  transport.

Edit `frontend/.env.production` and fill in `VITE_API_URL`/`VITE_APP_URL` with the real domain.
Both are baked into the client bundle at build time (never put a secret behind a `VITE_` prefix).

### Sanctum / session cookies in production

Cross-origin SPA cookie auth over real HTTPS needs three settings to agree, or login will appear
to succeed (200 response) but the session cookie will be silently rejected by the browser:

- `SESSION_SECURE_COOKIE=true` — required once serving over HTTPS (the whole point of this
  section) so the cookie is `Secure`-flagged.
- `SESSION_SAME_SITE` — `lax` is correct and simplest if the frontend and API are served from the
  **same registrable domain** (e.g. both under `clinic.example.com`, even on different
  subdomains/paths — this is the recommended topology, see nginx config below, which serves both
  from one origin). Only use `none` (which additionally requires `SESSION_SECURE_COOKIE=true`) if
  the frontend and API are deliberately deployed on two different top-level domains.
- `SANCTUM_STATEFUL_DOMAINS` — must list the exact frontend domain (no scheme, include port only
  if non-standard) for Sanctum to treat its requests as "stateful" (cookie-based) rather than
  rejecting them.

### First boot

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate
```

Store the generated `APP_KEY` value somewhere durable (secrets manager, encrypted backup of
`.env`) — regenerating it later invalidates every session and any encrypted column. Then, once
per environment (not on every restart):

```bash
cd frontend && npm ci && npm run build && cd ..   # produces frontend/dist/, which nginx serves
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

Migrations are a deliberate, explicit, operator-run step (not automatic on container boot — see
`docker/php/entrypoint.prod.sh`), so a crash-looping container can never hammer `migrate`
repeatedly, and a deploy that only changes application code (no new migration) never risks
touching the schema.

### First Admin User

Production must never contain the local demo accounts (`admin@example.com` / `password`, etc.) —
`backend/database/seeders/DatabaseSeeder.php` gates all of them behind
`app()->environment('local')`, so `php artisan db:seed` in production only seeds
`AppointmentTypeSeeder` (real reference data every clinic needs — Consultation/Cleaning/etc. — not
demo noise) and creates zero user accounts. Create the real first admin interactively instead:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan app:create-admin
```

This prompts for name, email, and a hidden (non-echoed) password, validates the password against
Laravel's default strength rules, and creates exactly one `admin`-role user — no known/default
credential ever touches the production database. Additional users (dentists, receptionists) should
be created afterward through the app's own Users screen, logged in as this admin.

### Docker production topology

`docker-compose.prod.yml` (repo root) differs from the dev compose file in a few
security/performance-relevant ways:

- `postgres` and `redis` publish **no host ports** — reachable only from other containers on the
  `dentalsuite` network. Use `docker compose -f docker-compose.prod.yml exec postgres psql ...`
  for direct DB access, never a directly-exposed port.
- `nginx` binds to `127.0.0.1:8000` only, not `0.0.0.0` — a host-level reverse proxy terminates
  TLS and forwards to it (see "SSL / TLS" below). It is never directly internet-facing.
- `nginx` serves the frontend's built static files (`frontend/dist/`, bind-mounted read-only) at
  `/`, and reverse-proxies `/api`, `/sanctum`, and `/up` to the `app` container's php-fpm — see
  `docker/nginx/default.prod.conf`. Frontend and API are served from **one origin**, which is why
  `SESSION_SAME_SITE=lax` (not `none`) is correct by default.
- `app` uses `docker/php/Dockerfile.prod` — adds a production `opcache.prod.ini`
  (`validate_timestamps=0`, so a container restart, not just a file edit, is required to pick up
  new code — expected for an immutable-per-boot deploy) and an entrypoint
  (`docker/php/entrypoint.prod.sh`) that refuses to start if `.env` is missing, `APP_KEY` is
  unset, or `APP_ENV=local` — guards against accidentally pointing prod at a dev config.
- All services use `restart: unless-stopped`.

### SSL / TLS

The Docker stack's `nginx` deliberately only speaks plain HTTP on `127.0.0.1:8000`. Terminate TLS
with a host-level reverse proxy in front of it — simplest, lowest-maintenance option for a
single-VPS deployment:

```bash
sudo apt install nginx certbot python3-certbot-nginx
```

Host nginx site config (`/etc/nginx/sites-available/dentalsuite`):

```nginx
server {
    listen 80;
    server_name clinic.example.com;
    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/dentalsuite /etc/nginx/sites-enabled/
sudo certbot --nginx -d clinic.example.com   # obtains cert, rewrites the above to redirect :80→:443
sudo systemctl reload nginx
```

Certbot installs its own renewal cron/systemd timer — verify with `sudo certbot renew --dry-run`.

### Deploying an update

```bash
cd /opt/dentalsuite
git pull
docker compose -f docker-compose.prod.yml build app
(cd frontend && npm ci && npm run build)
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force   # only if the update includes a new migration
```

### Rollback procedure

1. `git log --oneline` to find the last known-good commit/tag.
2. `git checkout <previous-tag-or-commit>`.
3. Rebuild and restart: `docker compose -f docker-compose.prod.yml build app && docker compose -f docker-compose.prod.yml up -d`, and rebuild the frontend the same way as a normal deploy.
4. **Database**: only run a new migration forward; do not blindly `migrate:rollback` against
   production (Laravel's `down()` methods are not exercised by the automated test suite the same
   way `up()` is, and a destructive `down()` on live data is riskier than the bug being rolled
   back from, in most cases). If the bad deploy included a schema change that must be undone,
   restore from the most recent backup taken *before* that deploy instead — see "Restore
   procedure" below — rather than trusting an untested `down()`.

---

## Backup & Recovery

### What's backed up

- **Database** — full Postgres dump (`pg_dump --format=custom`, restorable with `pg_restore`,
  includes schema + all data).
- **Storage** — `backend/storage/app/private` (patient file attachments once the Imaging module
  ships; currently near-empty, but backed up from day one so the pipeline is proven before there's
  anything valuable in it).

### Backup schedule

`docker/scripts/backup.sh` (run on the VPS host, not inside a container) dumps both into
`/var/backups/dentalsuite/` and prunes anything older than 14 days (override with
`DENTALSUITE_BACKUP_RETENTION_DAYS`). Schedule it nightly via cron:

```bash
chmod +x docker/scripts/backup.sh docker/scripts/restore.sh
sudo crontab -e
# 0 3 * * *  /opt/dentalsuite/docker/scripts/backup.sh >> /var/log/dentalsuite-backup.log 2>&1
```

### Offsite backup (S3-compatible)

Local-disk backups alone don't survive VPS loss or disk failure. Treat offsite backup as a
pre-launch requirement, not a nice-to-have, given patient data is involved.

`backup.sh` is already S3-ready — activating it is a config-only step, no code change:

1. **Provision a bucket** on any S3-compatible provider. Backblaze B2 or Wasabi are cheap,
   reliable options for this scale (a single clinic's DB + attachments); AWS S3 works identically.
   Create a bucket dedicated to backups (don't reuse the app's own file-storage bucket, if one
   exists — different lifecycle, different blast radius if either credential leaks).
2. **Create an access key scoped only to that bucket** (write + list, delete optional for
   lifecycle rules) — never reuse the application's own `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY`
   from `backend/.env` for backups; a compromised app credential should not also be able to touch
   backup history.
3. **Install the AWS CLI** on the VPS: `sudo apt install awscli` (or `pip install awscli`).
4. **Set three environment variables** in whatever shell context cron runs `backup.sh` in — the
   simplest approach is a small sourced file, e.g. `/etc/dentalsuite-backup.env`:

   ```bash
   BACKUP_S3_BUCKET=my-clinic-backups
   # BACKUP_S3_ENDPOINT=https://s3.us-west-000.backblazeb2.com   # omit entirely for AWS S3
   AWS_ACCESS_KEY_ID=...
   AWS_SECRET_ACCESS_KEY=...
   AWS_DEFAULT_REGION=us-west-000
   ```

   ```bash
   sudo crontab -e
   # 0 3 * * *  set -a; . /etc/dentalsuite-backup.env; set +a; /opt/dentalsuite/docker/scripts/backup.sh >> /var/log/dentalsuite-backup.log 2>&1
   ```

5. **Verify**: run `backup.sh` manually once and confirm `aws s3 ls s3://my-clinic-backups/dentalsuite/database/` lists the new dump. Until `BACKUP_S3_BUCKET` is set, the script logs a clear warning and continues with local-only backup — it never fails silently.

### Restore procedure

```bash
docker/scripts/restore.sh /var/backups/dentalsuite/database/dentalsuite-<TIMESTAMP>.dump \
                           /var/backups/dentalsuite/storage/dentalsuite-storage-<TIMESTAMP>.tar.gz
```

This puts the app into maintenance mode, drops and recreates the database, restores from the dump,
replaces `storage/app/private`, then brings the app back up. It requires an explicit `y`
confirmation and never guesses "latest" — a restore is destructive by nature and should never run
from a typo'd or half-remembered command.

**After every restore, verify before considering it done**: log in as an existing user, open a
patient record, check the dashboard's patient/appointment counts match what's expected for that
backup's timestamp. A backup that "exists" but has never been restored is not a real backup.

### Testing restore (do this before going live, then quarterly)

Restoring is only proven safe if it's been exercised outside a real emergency. `docker-compose.rehearsal.yml`
(repo root) is a permanent, committed, throwaway stack for exactly this — an isolated Postgres +
app pair on its own Docker network with no host ports and an anonymous volume, so a rehearsal can
never collide with or accidentally touch the real dev or production stack:

```bash
# 1. Take a real backup from whichever stack currently has data (dev stack shown; swap
#    docker-compose.yml for docker-compose.prod.yml against a real production host).
COMPOSE_FILE=docker-compose.yml docker/scripts/backup.sh

# 2. Bring up the isolated rehearsal target (fresh Postgres, migrations run automatically on boot).
docker compose -f docker-compose.rehearsal.yml up -d --build

# 3. Run the *actual, unmodified* restore.sh against it — proves the real production script works,
#    not a hand-rolled substitute.
COMPOSE_FILE=docker-compose.rehearsal.yml ENV_FILE=backend/.env.rehearsal.example \
  docker/scripts/restore.sh <path-to-.dump> <path-to-storage.tar.gz>

# 4. Verify — row counts must match the source exactly, and spot-check at least one full record.
docker compose -f docker-compose.rehearsal.yml exec postgres \
  psql -U dentalsuite_rehearsal -d dentalsuite_rehearsal -c "SELECT count(*) FROM patients;"

# 5. Tear down completely — nothing here is meant to persist.
docker compose -f docker-compose.rehearsal.yml down -v
```

**Last verified: 2026-07-18.** Ran the full sequence above against a live dev-stack backup (9
patients, 3 users, 6 appointment types, 0 appointments). Every table's row count matched the
source exactly after restore; the first patient record (by `sequence_number`) was spot-checked
field-by-field (UUID, name, phone, national ID) and matched byte-for-byte. The storage tar
extracted correctly into `storage/app/private`. The source dev stack was confirmed unaffected
throughout (`SELECT count(*) FROM patients` still returned 9 immediately after the rehearsal's
teardown) — the isolated-target design means this rehearsal carries zero risk to whatever stack
the backup was taken from. Re-run this against a real `docker-compose.prod.yml`-backed backup once
the system is live, and update this line with that date.

---

## Production Security Checklist

Run through this before the first real deployment, and again after any change to auth,
CORS, or the Docker topology:

**Backend**
- [ ] `backend/.env` has `APP_DEBUG=false`, `APP_ENV=production`, `LOG_LEVEL=error` (never copy
      `backend/.env.example` — that's the dev template; use `.env.production.example`).
- [ ] `SESSION_SECURE_COOKIE=true`, `SANCTUM_STATEFUL_DOMAINS` set to the real frontend domain.
- [ ] `config/cors.php`'s `allowed_origins` resolves to the real `FRONTEND_URL` only (no
      wildcard, no leftover `localhost`).
- [ ] General API rate limiting is active (`bootstrap/app.php`'s `throttleApi()` +
      `AppServiceProvider`'s `RateLimiter::for('api', ...)`, 120 req/min per user/IP) on top of
      `/login`'s dedicated 5-attempts/60s limiter.
- [ ] Every model exposed via a controller has a matching Policy (`app/Policies/`) — verified for
      `Appointment`, `AppointmentType`, `Patient`, `User`, `DentistTimeOff`,
      `DentistWorkingHour` as of the 2026-07-18 production gate audit.
- [ ] `storage/` and `bootstrap/cache/` are writable by `www-data` inside the container (the
      entrypoint `chown`s them on every boot) but not web-accessible (`config/filesystems.php`'s
      `local` disk root is `storage/app/private`, outside `public/`).
- [ ] No `db:seed` run against production beyond the default `AppointmentTypeSeeder` — first admin
      created via `php artisan app:create-admin` (see above), never a factory/seeder password.
**Frontend**
- [ ] `frontend/.env.production` has real `VITE_API_URL`/`VITE_APP_URL` (both public — never put a
      secret behind a `VITE_` prefix, since anything so-prefixed ships in the client bundle).
- [ ] `npm run build` output has no source maps (`vite.config.ts` sets `build.sourcemap: false`
      explicitly).
- [ ] No `localStorage`/`sessionStorage` use for auth state — Sanctum's httpOnly session cookie is
      the only credential; verified via `frontend/src/lib/api.ts`'s `withCredentials: true` +
      `/sanctum/csrf-cookie` flow.
- [ ] Error handlers surface translated, user-facing messages — never raw
      `error.message`/`error.response.data` dumped straight into the UI.

---

## Performance

- **Bundle size**: `npm run build` reports each chunk's size — watch for regressions on every
  release. `vue-i18n`'s full compiler+runtime build is currently the largest non-vendor chunk
  (~84 KB gzipped, loaded on every page) because locale JSON is compiled to render functions at
  runtime rather than at build time. Not blocking a first production deploy, but tracked as a
  follow-up: add `@intlify/unplugin-vue-i18n` to `vite.config.ts` to precompile
  `locales/*.json` at build time, then switch to `vue-i18n`'s runtime-only build. See
  `TECH_DEBT.md`.
- **Backend**: `opcache.prod.ini` (`docker/php/Dockerfile.prod`) enables opcache with
  `validate_timestamps=0` and the JIT — a meaningful, low-risk win for a PHP-FPM production
  workload with no runtime code-reload requirement.
- **Lighthouse**: run `npx lighthouse https://clinic.example.com --view` against a real deployed
  instance (not `localhost`, since throttled network/CPU checks are more representative against
  the actual production build+hosting) before onboarding the first real clinic, and periodically
  thereafter as new modules ship.

---

## CI/CD Quality Gate

`.github/workflows/ci.yml` runs on every push/PR and must pass before merging: backend tests
(`php artisan test`), Larastan, Pint, frontend unit tests (`npm run test`), `vue-tsc`, ESLint, and
a production `npm run build`. Every future module must keep this gate green — it is the
enforcement mechanism for "same quality bar as Appointments" going forward.
