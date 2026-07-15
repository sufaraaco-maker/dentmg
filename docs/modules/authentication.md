# Authentication Module

## Scope (V1)

تسجيل دخول/خروج للمستخدمين الموجودين فعليًا في جدول `users` (لا يوجد تسجيل ذاتي/Registration — إنشاء المستخدمين هيتم من module الـ Users القادم من طرف admin). المصادقة SPA-cookie-based عبر Laravel Sanctum (Stateful).

## Decision Log

- **Auth mechanism**: Sanctum SPA (cookie/session) بدل API tokens — لأن الـ frontend وHTML backend شغالين كـ first-party SPA على نفس الـ `localhost` (بورتات مختلفة: `:5173` و`:8000`). آمن ولا يحتاج تخزين توكن في `localStorage`.
- **UUID**: تم تحويل جدول `users` (وجدول `sessions`) من `bigint auto-increment` إلى `uuid` قبل بناء هذا الموديول، عشان يطابق قاعدة `PROJECT_CONTEXT.md` (UUID لكل الجداول)، ولأن تأجيل هذا التحويل بيصير أصعب بعد ما جداول تانية تبدأ تعتمد عليه.

## Backend

| الملف | الدور |
|---|---|
| `database/migrations/0001_01_01_000000_create_users_table.php` | `users.id` و`sessions.user_id` كـ UUID |
| `app/Models/User.php` | يستخدم `HasUuids` |
| `app/Http/Requests/Auth/LoginRequest.php` | تحقق من `email`/`password` + `throttleKey()` |
| `app/Services/AuthService.php` | منطق تسجيل الدخول/الخروج، Rate limiting (5 محاولات/دقيقة عبر `RateLimiter` الأصلي في Laravel)، `session()->regenerate()` بعد نجاح الدخول |
| `app/Http/Controllers/Api/AuthController.php` | Controller نحيف: `login`, `logout`, `user` |
| `app/Http/Resources/UserResource.php` | تمثيل موحّد للمستخدم في الـ API |
| `routes/api.php` | `POST /api/login`, `POST /api/logout` و`GET /api/user` (خلف `auth:sanctum`) |
| `config/cors.php` | `supports_credentials = true`, `paths` تشمل `sanctum/csrf-cookie` |
| `bootstrap/app.php` | `$middleware->statefulApi()` لتفعيل جلسات Sanctum على مجموعة الـ `api` |
| `.env` → `SANCTUM_STATEFUL_DOMAINS` | نطاقات الـ frontend المسموح لها بمصادقة الكوكيز (`localhost:5173`) |
| `tests/Feature/AuthTest.php` | تسجيل دخول ناجح/فاشل، rate بيانات ناقصة، حماية `/api/user`، تسجيل خروج |

## Policy

لا يوجد Policy بعد — نفس منطق module الـ Dashboard. التفويض المبني على الأدوار (Roles/Permissions) هيتضاف مع module الـ Roles & Permissions القادم بعد Users. حاليًا كل مستخدم مسجّل دخول له نفس الصلاحيات.

## Frontend

| الملف | الدور |
|---|---|
| `src/lib/api.ts` | `withCredentials: true`, `withXSRFToken: true`, `fetchCsrfCookie()` |
| `src/stores/auth.ts` | Pinia store: `user`, `isAuthenticated`, `login()`, `logout()`, `fetchUser()` |
| `src/views/LoginView.vue` | فورم دخول (PrimeVue) |
| `src/router/index.ts` | `beforeEach` guard: يحمّل المستخدم الحالي مرة واحدة، يحوّل لصفحة الدخول إذا الصفحة تتطلب مصادقة |
| `src/layouts/DefaultLayout.vue` | زر تسجيل الخروج |
| `src/locales/{ar,en,tr}.json` | مفاتيح `auth.*` |

## API

```
GET /sanctum/csrf-cookie          → يضبط كوكي XSRF-TOKEN (لازم يُستدعى قبل login)

POST /api/login
{ "email": "...", "password": "..." }
200 OK { "id": "uuid", "name": "...", "email": "..." }
422 Unprocessable { "errors": { "email": ["..."] } }

POST /api/logout   (auth:sanctum)
204 No Content

GET /api/user       (auth:sanctum)
200 OK { "id": "uuid", "name": "...", "email": "..." }
401 Unauthorized
```

## Next Steps

- Roles/Permissions + Policies تتضاف مع module الـ Roles & Permissions.
- إنشاء/إدارة المستخدمين (بدل الاعتماد على الـ Seeder) يتضاف مع module الـ Users.
- Forgot/Reset password غير مطلوب في V1 (يُقيّم لاحقًا).
