# Users Module

## Scope (V1)

إدارة حسابات المستخدمين (الطاقم): عرض قائمة، بحث، إنشاء، تعديل، حذف (Soft Delete). لا يوجد تسجيل ذاتي — الحسابات تُنشأ فقط من طرف مستخدم آخر مسجّل دخول عبر هذا الموديول.

## Backend

| الملف | الدور |
|---|---|
| `database/migrations/2026_07_11_000001_add_soft_deletes_to_users_table.php` | يضيف `deleted_at` لجدول `users` |
| `app/Models/User.php` | يستخدم `SoftDeletes` |
| `app/Http/Requests/User/StoreUserRequest.php` | تحقق الإنشاء + تفويض عبر `UserPolicy::create` |
| `app/Http/Requests/User/UpdateUserRequest.php` | تحقق التعديل (حقول اختيارية) + تفويض عبر `UserPolicy::update`، يتجاهل إيميل المستخدم نفسه عند فحص التكرار |
| `app/Services/UserService.php` | Pagination + بحث بالاسم/الإيميل، إنشاء (hash password)، تعديل، حذف ناعم |
| `app/Policies/UserPolicy.php` | كل مستخدم مسجّل دخول له كامل الصلاحيات حاليًا، ما عدا حذف حسابه هو نفسه (`delete` تمنع ذلك) |
| `app/Http/Controllers/Api/UserController.php` | Controller نحيف (index/store/show/update/destroy) |
| `routes/api.php` | `Route::apiResource('users', UserController::class)` خلف `auth:sanctum` |
| `tests/Feature/UserTest.php` | CRUD كامل، بحث، تكرار إيميل، منع حذف الحساب الشخصي، منع دخول مستخدم محذوف (soft-deleted) |

## Policy

محدّثة مع module الـ [Roles & Permissions](roles-permissions.md): `viewAny`/`view` مفتوحة لأي مستخدم مسجّل دخول (رؤية قائمة الطاقم مش حساسة)، أما `create`/`update`/`delete` مقصورة على مستخدمين بدور `admin` فقط — بالإضافة للقيد القديم إن الـ admin نفسه ما يقدر يحذف حسابه.

## Frontend

| الملف | الدور |
|---|---|
| `src/views/UsersView.vue` | جدول (PrimeVue `DataTable`) مع بحث، إنشاء/تعديل عبر Dialog، حذف مع تأكيد |
| `src/router/index.ts` | مسار `/users` (محمي بـ `requiresAuth`) |
| `src/layouts/DefaultLayout.vue` | رابط تنقّل لصفحة المستخدمين |
| `src/locales/{ar,en,tr}.json` | مفاتيح `users.*` |

## API

```
GET /api/users?search=...        (auth:sanctum, أي دور)
200 OK
{
  "data": [{ "id": "uuid", "name": "...", "email": "...", "role": "admin" }],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 1, ... }
}

POST /api/users                  (auth:sanctum, admin فقط)
{ "name": "...", "email": "...", "password": "...", "password_confirmation": "...", "role": "dentist" }
201 Created { "id": "uuid", "name": "...", "email": "...", "role": "dentist" }
403 Forbidden  → إذا الفاعل مش admin

GET /api/users/{user}            (auth:sanctum, أي دور)
PUT/PATCH /api/users/{user}      (auth:sanctum, admin فقط)
DELETE /api/users/{user}         (auth:sanctum, admin فقط)
204 No Content
403 Forbidden  → إذا الفاعل مش admin، أو عند محاولة حذف الحساب الشخصي
```

## Next Steps

- ربط المستخدم بفرع (Multi Branch) إذا ظهرت الحاجة الفعلية لاحقًا — لم يُبنَ في V1 لتفادي التعقيد قبل أوانه.
