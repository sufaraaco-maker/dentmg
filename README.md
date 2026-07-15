# DentalSuite

نظام إدارة عيادات أسنان — Laravel 12 + Vue 3. راجع [PROJECT_CONTEXT.md](./PROJECT_CONTEXT.md) للمرجع الكامل للمعمارية والفلسفة.

## هيكل المشروع

```
backend/    Laravel 12 API (PHP 8.4)
frontend/   Vue 3 + TypeScript + PrimeVue + Tailwind
docker/     Dockerfiles وإعدادات nginx
```

## المتطلبات

- Docker Desktop (مع WSL2 على ويندوز)

## التشغيل

```bash
docker compose up -d --build
```

عند أول تشغيل، الحاوية `app` تلقائيًا:
- تثبت حزم Composer
- تولّد `APP_KEY`
- تشغّل الـ migrations

## نقاط الوصول

| الخدمة | الرابط |
|---|---|
| Backend API | http://localhost:8000 |
| Frontend | http://localhost:5173 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |

## أوامر مفيدة

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app composer install
docker compose logs -f app
```
