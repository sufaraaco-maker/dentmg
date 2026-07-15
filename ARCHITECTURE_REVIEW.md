# Architecture Review — DentalSuite

تاريخ التقرير: 2026-07-11
يغطي هذا التقرير كل الشغل المنفّذ حتى الآن (Dashboard, Authentication, Users, Roles & Permissions)، قبل البدء بموديول Patients.

**ملاحظة مهمة**: لا يوجد أي commit بعد (`git log` فاضي، الفرع `master` بدون تاريخ). كل الملفات لسه untracked. يعني كل شي بهذا التقرير قابل للمراجعة/التراجع بالكامل قبل أول commit.

---

## 1. هيكل المشروع (Project Structure)

Monorepo بثلاث مجلدات رئيسية، مطابق لما هو موثّق بـ `PROJECT_CONTEXT.md`:

```
DentalSuite/
├── backend/          Laravel 12 API (PHP 8.4، عبر Docker — PHP المحلي 8.0.18 غير كافي)
├── frontend/         Vue 3 + TypeScript + PrimeVue + Tailwind
├── docker/           Dockerfiles + nginx config
├── docker-compose.yml
├── PROJECT_CONTEXT.md
└── README.md
```

الخدمات (`docker-compose.yml`):

| Service | Image | المنفذ |
|---|---|---|
| `app` | مبني من `docker/php/Dockerfile` (php:8.4-fpm-alpine) | 9000 (داخلي فقط) |
| `nginx` | nginx:stable-alpine | 8000 → Backend API |
| `node` | node:22-alpine (`npm run dev -- --host`) | 5173 → Frontend |
| `postgres` | postgres:17-alpine | 5432 |
| `redis` | redis:7-alpine | 6379 |

---

## 2. Folder Structure (تفصيلي)

### Backend (`backend/`)

```
app/
├── Enums/
│   └── UserRole.php                      Admin | Dentist | Receptionist (backed enum)
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php                base — يحمل AuthorizesRequests
│   │   └── Api/
│   │       ├── AuthController.php
│   │       ├── DashboardController.php
│   │       └── UserController.php
│   ├── Requests/
│   │   ├── Auth/LoginRequest.php
│   │   └── User/{Store,Update}UserRequest.php
│   └── Resources/
│       └── UserResource.php
├── Models/
│   └── User.php                          UUID + SoftDeletes + role cast
├── Policies/
│   └── UserPolicy.php
├── Providers/
│   └── AppServiceProvider.php            JsonResource::withoutWrapping()
└── Services/
    ├── AuthService.php                   login/logout + rate limiting
    ├── DashboardService.php
    └── UserService.php

database/
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php   ← مُعدَّل (UUID بدل bigint)
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2026_07_11_000001_add_soft_deletes_to_users_table.php   ← جديد
│   └── 2026_07_11_000002_add_role_to_users_table.php           ← جديد
├── factories/UserFactory.php             + admin()/dentist() states
└── seeders/DatabaseSeeder.php

routes/api.php                            كل الـ endpoints ما عدا /ping و/login خلف auth:sanctum
config/cors.php                           جديد (ما كان موجود بالسكيلتون الافتراضي)
tests/Feature/{AuthTest,DashboardTest,UserTest}.php
docs/modules/{authentication,users,roles-permissions,dashboard}.md
```

### Frontend (`frontend/src/`)

```
lib/api.ts               axios instance + fetchCsrfCookie()
types/user.ts             AuthUser, UserRole, USER_ROLES        ← جديد
stores/
├── auth.ts               user, isAuthenticated, isAdmin, login/logout/fetchUser
└── ui.ts                 theme + locale (موجود مسبقًا)
router/index.ts            /login (guest-only), /users, / (dashboard) — كلها خلف guard
layouts/DefaultLayout.vue  header + nav (Dashboard/Users) + logout — النav bar كانت فاضية قبل اليوم
views/
├── DashboardView.vue      (موجود مسبقًا)
├── LoginView.vue          جديد
├── UsersView.vue          جديد (DataTable + Dialog CRUD)
└── NotFoundView.vue       (موجود مسبقًا)
locales/{ar,en,tr}.json     auth.*, users.*, nav.users, common.cancel/save
```

---

## 3. Database Strategy

| القرار | التفاصيل | مصدره |
|---|---|---|
| **UUID كمفتاح أساسي** | `users.id` و`sessions.user_id` تحوّلوا من `bigint auto-increment` لـ `uuid` (عبر `HasUuids` trait) | مطلوب صراحة بـ `PROJECT_CONTEXT.md` ("UUID")، اتفقنا عليه معك قبل بناء Authentication بدل ما نأجله |
| **Soft Deletes** | `users` عندها `deleted_at`. الحذف = soft delete، والمستخدم المحذوف ما يقدر يسجّل دخول (query scope الافتراضي لـ Eloquent بيستثنيه تلقائيًا) | مطلوب بـ `PROJECT_CONTEXT.md` ("Soft Deletes") — **قرار اتخذته أنا وقت بناء موديول Users بدون سؤالك مباشرة**، مبني على النص الصريح بالوثيقة |
| **الأدوار كـ enum بسيط** | عمود `role` (string) على `users`، cast لـ PHP Backed Enum. بدون جداول roles/permissions منفصلة | اتفقنا عليه معك صراحة (سؤال AskUserQuestion) |
| **Audit Logs, Multi Branch** | **لسه ما اتنفذوا** | موثّقين بـ `PROJECT_CONTEXT.md` بس ما وصلنا لهم — أول اختلاف حقيقي عن الوثيقة (تفصيل بقسم 7) |
| **PostgreSQL 17** | كل الجداول والـ migrations متوافقة، `->change()` على الأعمدة شغّال native بدون doctrine/dbal (Laravel 11+) | مطابق للمخطط |

---

## 4. Authentication Strategy

- **الآلية**: Laravel Sanctum SPA (cookie/session-based) — مو API tokens. اتفقنا عليه معك صراحة.
- **السبب**: الـ frontend (`localhost:5173`) والـ backend (`localhost:8000`) على نفس الـ host بس بورتات مختلفة → first-party SPA كلاسيكي، الحل الرسمي من Laravel لهذه الحالة.
- **التدفق الفعلي**:
  1. `GET /sanctum/csrf-cookie` → يضبط `XSRF-TOKEN` + `dentalsuite-session` cookies.
  2. `POST /api/login` مع `X-XSRF-TOKEN` header → `Auth::attempt()` + `session()->regenerate()`.
  3. أي طلب بعدها يعتمد على الكوكيز (`withCredentials: true` + `withXSRFToken: true` بالـ axios instance).
- **الحماية من brute-force**: Rate limiting داخل `AuthService` (5 محاولات/60 ثانية لكل `email+ip`) — **إضافة مني، ما كانت مطلوبة صراحة**، لكنها معيار أمان أساسي لأي endpoint دخول.
- **CORS**: `config/cors.php` (ملف جديد كليًا، ما كان موجود بالسكيلتون) — `supports_credentials: true`, origins من `FRONTEND_URL`.
- **الـ Middleware**: `bootstrap/app.php` يستخدم `$middleware->statefulApi()` (طريقة Laravel 12 الرسمية لتفعيل session middleware على مجموعة الـ `api` عند الطلبات القادمة من نطاق موثوق).
- **الصلاحيات (Authorization)**: بُنيت لاحقًا مع موديول Roles & Permissions — `UserPolicy` هي نقطة التطبيق الوحيدة حاليًا (admin فقط يقدر يدير المستخدمين).

---

## 5. API Structure

كل الـ routes بـ `routes/api.php`، REST-ish، بدون أي envelope إضافي (`JsonResource::withoutWrapping()` مفعّلة globally — قرار مني، شرح بقسم 6).

```
GET   /api/ping                          عام (health check)
POST  /api/login                         عام

# كل شي تحت هون يتطلب auth:sanctum
POST   /api/logout
GET    /api/user                         بيانات المستخدم الحالي (بما فيها role)
GET    /api/dashboard/summary            نُقل لهون اليوم — كان عام بالخطأ (قسم 7)

GET    /api/users        ?search=&page=  Pagination (data/links/meta) — أي دور
POST   /api/users                        admin فقط
GET    /api/users/{id}                   أي دور
PUT    /api/users/{id}                   admin فقط
DELETE /api/users/{id}                   admin فقط، وممنوع حذف الحساب الشخصي
```

- **Pagination**: `UserResource::collection()` مع Laravel's `LengthAwarePaginator` → شكل قياسي `{data, links, meta}` (استثناء وحيد من قاعدة "بدون envelope" لأنه بنيوي/ضروري للـ pagination).
- **Validation**: عبر `FormRequest` classes (`LoginRequest`, `Store/UpdateUserRequest`) — منطق التفويض (`authorize()`) داخل الـ Request نفسه بيستدعي الـ Policy.
- **Error shape**: قياسي لـ Laravel (`422` مع `errors: {field: [...]}`، `403` مع `message`، `401` مع `message: "Unauthenticated."`).

---

## 6. أهم القرارات التقنية

| # | القرار | لماذا | هل كان مطلوب صراحة؟ |
|---|---|---|---|
| 1 | Sanctum SPA cookie auth | first-party SPA، Laravel-native | ✅ اتفقنا عليه |
| 2 | UUID لكل الجداول | مطلوب بالوثيقة، أرخص نغيّره الآن قبل ما جداول تانية تعتمد عليه | ✅ اتفقنا عليه |
| 3 | Roles كـ enum بسيط بدل جداول | كفاية لعيادة واحدة (Single Organization)، v1 بسيط | ✅ اتفقنا عليه |
| 4 | Soft Deletes على `users` | نص صريح بالوثيقة | ⚠️ نفّذته بدون سؤال مباشر — مبرَّر بالوثيقة |
| 5 | Rate limiting على `/login` | معيار أمان أساسي، Laravel-native (`RateLimiter` facade) | ❌ إضافة مني بالكامل |
| 6 | `JsonResource::withoutWrapping()` عام | ليتطابق شكل استجابات Auth/Users مع Dashboard (اللي كان أصلاً بدون envelope) | ❌ قرار مني، **يؤثر على كل موديول قادم** |
| 7 | `AuthorizesRequests` على base Controller | لازم لاستخدام `$this->authorize()` — Laravel 12 skeleton ما يجيبها افتراضيًا | ❌ إضافة مني (تقنية بحتة، بدون بديل) |
| 8 | نقل `/api/dashboard/summary` خلف `auth:sanctum` | كانت ثغرة — endpoint عام بالخطأ من يوم ما بنينا Authentication | ⚠️ إصلاح لخطأ سابق مني، غير مطلوب بهذه الرسالة تحديدًا |
| 9 | إضافة nav bar بـ `DefaultLayout.vue` | ما كان في أي رابط تنقّل بالواجهة أصلاً (بس header فاضي) | ❌ إضافة مني لجعل صفحة Users قابلة للوصول |
| 10 | `ConfirmationService` + `ToastService` بـ `main.ts` | مطلوبين تقنيًا لـ confirm-delete + toast notifications بصفحة Users | ❌ إضافة مني (ضرورية للميزة، مو اختيارية) |
| 11 | `config/app.php` → مفتاح `frontend_url` جديد | احتجته بالـ testing (`TestCase.php`) عشان أحاكي Referer header متوافق مع Sanctum stateful check | ❌ إضافة مني |
| 12 | تعديل `tests/TestCase.php` (Referer header افتراضي لكل الاختبارات) | بدونه أي اختبار Feature يلمس session-authenticated route بيفشل (Sanctum بيتطلب Referer/Origin يطابق stateful domain) | ❌ إضافة مني (ضرورة تقنية) |

---

## 7. أي اختلاف عن PROJECT_CONTEXT.md

### أ. أشياء موثّقة بالوثيقة ولسه ما اتنفذت (مو اختلاف، بس فجوة حاليًا معروفة)

- **Audit Logs**: مذكورة بقسم Database، ما بنينا أي آلية تسجيل تدقيق (مين عدّل شنو ومتى) لأي موديول لحد الآن. أول موديول حسّاس لهيك شي رح يكون Patients أو Billing.
- **Multi Branch**: مذكورة بقسم Database، ما ربطنا `users` (أو أي جدول) بفرع. أجّلناها بوعي (موثّق بـ `users.md`) لحد ما تظهر حاجة فعلية.
- **Dark Mode / RTL**: موجودين فعليًا (theme toggle + locale switch مع RTL للعربي) — **متوافق**، مو فجوة.

### ب. قرارات تقنية أضفناها ولا وجود صريح لها بالوثيقة

- Rate limiting، `JsonResource::withoutWrapping()`، nav bar، Toast/Confirm services — كل هذول تفاصيل تنفيذ طبيعية ما كانت الوثيقة رح تنزل لهذا المستوى من التفصيل أصلاً (الوثيقة معمارية عامة مو implementation spec). ما فيها تعارض مع أي بند بالوثيقة، بس هي قرارات اتخذتها أنا بدون رجوع ليك أولاً.

### ج. أقرب شي لـ "مخالفة" فعلية

- **لا يوجد**. كل قرار له تبرير مباشر إما من نص الوثيقة (UUID, Soft Deletes) أو ضرورة تقنية بحتة ما فيها بديل معماري تاني (AuthorizesRequests trait, rate limiting كممارسة أمان قياسية). ولا قرار واحد يعارض بند صريح بـ `PROJECT_CONTEXT.md`.

---

## 8. خلاصة قبل البدء بـ Patients

- 26 اختبار (PHPUnit) كلهم ناجحين، Pint نضيف.
- كل موديول مبني حسب القائمة المطلوبة بـ "Development Strategy": Migration, Model, Validation, Service, Policy, API, Vue Pages, Tests, Documentation.
- لا commit واحد لحد الآن — كل شي بهذا التقرير قابل للتراجع أو التعديل بالكامل.
- التوصية: **مراجعة الجدول بقسم 6** (خصوصًا البند #6 `withoutWrapping()` لأنه عام على كل الـ API القادمة) والموافقة عليه أو تغييره قبل ما نبني عليه موديولات أكتر.
