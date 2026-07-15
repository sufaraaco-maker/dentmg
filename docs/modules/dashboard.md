# Dashboard Module

## Scope (V1)

عرض ملخص سريع لأهم أرقام العيادة. بما إن modules زي Patients وAppointments وBilling لسه ما اتبنت، الإحصائيات ترجع صفر تلقائيًا لحد ما تلك الجداول تصير موجودة — بدون ما ينهار الـ endpoint.

## Backend

| الملف | الدور |
|---|---|
| `app/Services/DashboardService.php` | يجمّع الإحصائيات، يتحقق من وجود الجدول قبل العدّ (`Schema::hasTable`) |
| `app/Http/Controllers/Api/DashboardController.php` | Controller نحيف، يستدعي الـ Service فقط |
| `routes/api.php` | `GET /api/dashboard/summary` |
| `tests/Feature/DashboardTest.php` | يتحقق من بنية الاستجابة والقيم الافتراضية |

## Policy

لا يوجد Policy بعد — التفويض (authorization) هيتضاف مع module الـ Authentication/Roles القادم. حاليًا الـ endpoint مفتوح دون مصادقة.

## Frontend

| الملف | الدور |
|---|---|
| `src/lib/api.ts` | axios instance مركزي (`VITE_API_URL`) |
| `src/views/DashboardView.vue` | يجلب `/dashboard/summary` ويعرضها كبطاقات إحصائية (PrimeVue `Card`) |
| `src/locales/{ar,en,tr}.json` | مفاتيح `dashboard.stats.*` |

## API

```
GET /api/dashboard/summary

200 OK
{
  "total_patients": 0,
  "today_appointments": 0,
  "monthly_revenue": 0
}
```

## Next Steps

- عند بناء module Patients: `total_patients` هيرجع رقم حقيقي تلقائيًا (الكود جاهز، بس يتحقق من وجود الجدول).
- نفس الشي لـ Appointments.
- `monthly_revenue` هيتفعّل مع module Billing.
- Policy/Middleware للحماية هيتضاف مع module Authentication.
