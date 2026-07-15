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
docker compose logs -f app
```

## Production

Not yet defined — no production environment exists. To be documented when the project reaches a deployable milestone.
