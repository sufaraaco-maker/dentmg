# Roles & Permissions Module

## Scope (V1)

نظام أدوار بسيط وثابت (PHP Backed Enum) بدل جداول roles/permissions منفصلة — قرار موثّق مع المستخدم: العيادة عبارة عن منظمة واحدة (Single Organization، لا Multi Tenant)، وثلاثة أدوار كافية لتغطية الاحتياج الفعلي حاليًا. لو ظهرت حاجة فعلية لصلاحيات دقيقة (granular permissions) لاحقًا يُعاد التقييم وقتها.

## Decision Log

- **Enum ثابت بدل جداول**: أبسط، وأسرع، ويكفي لعيادة أسنان بمنظمة واحدة. تفادينا `spatie/laravel-permission` per "Prefer Laravel native solutions" و"Never introduce unnecessary packages" في `PROJECT_CONTEXT.md`.
- **الأدوار الثلاثة**: `admin`, `dentist`, `receptionist` — تغطي الأدوار الأساسية بعيادة أسنان. أي دور جديد مستقبلاً = إضافة `case` واحد للـ enum + تحديث الترجمات.

## Backend

| الملف | الدور |
|---|---|
| `app/Enums/UserRole.php` | Backed enum: `Admin`, `Dentist`, `Receptionist` |
| `database/migrations/2026_07_11_000002_add_role_to_users_table.php` | عمود `role` (string) بقيمة افتراضية `receptionist` |
| `app/Models/User.php` | `casts()` يحوّل `role` لـ `UserRole` تلقائيًا، + `isAdmin()` helper |
| `app/Http/Requests/User/{Store,Update}UserRequest.php` | تتحقق من `role` عبر `Rule::enum` |
| `app/Policies/UserPolicy.php` | **التطبيق الفعلي للصلاحيات**: `viewAny`/`view` مفتوحة لأي مستخدم مسجّل دخول، أما `create`/`update`/`delete` مقصورة على `admin` فقط (بالإضافة لمنع حذف الحساب الشخصي) |
| `routes/api.php` | تم نقل `GET /api/dashboard/summary` داخل مجموعة `auth:sanctum` — كانت هذه ثغرة متبقية من قبل بناء module الـ Authentication (الـ endpoint كان عام بدون حماية بالخطأ) |
| `database/seeders/DatabaseSeeder.php` + `UserFactory` | المستخدم التجريبي (`test@example.com`) دوره `admin`. الـ Factory تنشئ مستخدمين بدور `receptionist` افتراضيًا، مع state methods: `admin()`, `dentist()` |
| `tests/Feature/UserTest.php` | تحقق كامل: admin يقدر ينشئ/يعدّل/يحذف، غير الـ admin يُرفض بـ 403، أي مستخدم يقدر يشوف القائمة |
| `tests/Feature/DashboardTest.php` | تحقق إضافي إن الضيف (guest) ما يقدر يوصل للـ endpoint بعد الآن |

## Frontend

| الملف | الدور |
|---|---|
| `src/stores/auth.ts` | `user.role` متوفر الآن بعد تسجيل الدخول |
| `src/views/UsersView.vue` | حقل اختيار الدور بالفورم، عمود الدور بالجدول، أزرار الإنشاء/التعديل/الحذف تظهر فقط إذا `auth.user?.role === 'admin'` |
| `src/locales/{ar,en,tr}.json` | مفاتيح `users.roles.*` لعرض اسم الدور مترجم |

## API

كل استجابات المستخدم (`/api/login`, `/api/user`, `/api/users*`) صارت ترجع حقل `role` إضافي:

```json
{ "id": "uuid", "name": "...", "email": "...", "role": "admin" }
```

`POST /api/users` و`PUT/PATCH /api/users/{user}` يتطلبوا/يقبلوا `role` (قيمة من: `admin`, `dentist`, `receptionist`).

محاولة إنشاء/تعديل/حذف مستخدم من طرف غير admin → `403 Forbidden`.

## Next Steps

- لو احتجنا صلاحيات دقيقة على مستوى موديولات تانية (مثلاً: بس admin يقدر يحذف مريض، أو dentist بس يقدر يكتب Clinical Notes) هيتم التحقق منها بنفس الطريقة (`$actor->role === UserRole::X`) داخل الـ Policy المعنية لكل module، بدون حاجة لتوسعة نظام الأدوار نفسه إلا إذا ظهرت حاجة فعلية لصلاحيات مخصصة (custom permissions) تفوق الأدوار الثلاثة.
