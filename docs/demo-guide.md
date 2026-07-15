# DentalSuite — Demo Guide

Purpose: give a reviewer (or a future you) everything needed to start the system, log in, and walk through
every currently-working screen in under 5 minutes. Written after the 2026-07-16 system validation checkpoint
and demo-environment preparation — see `docs/decisions.md` and `TECH_DEBT.md` for the history behind it.

---

## 1. How to Start the System

Requirement: Docker Desktop running (WSL2 backend on Windows).

```bash
docker compose up -d --build
```

On first run, the `app` container's entrypoint automatically:
- installs Composer dependencies
- generates `APP_KEY` if missing
- runs database migrations

This does **not** automatically seed demo data (seeding is destructive if run against a database that
already has real data, so it's a deliberate manual step — see §5). For a fresh demo database:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

Wait roughly 30–60 seconds after `docker compose up` for the frontend's first `npm install` + Vite
cold-start (subsequent starts are much faster — Vite's dependency cache persists in the named volume).

**Access points:**

| Service | URL |
|---|---|
| Frontend (Vue SPA) | http://localhost:5173 |
| Backend API | http://localhost:8000 |
| PostgreSQL | localhost:5432 |
| Redis | localhost:6379 |

**Useful commands:**

```bash
docker compose ps                                  # confirm all 5 containers are Up
docker compose logs -f app                          # tail backend logs
docker compose exec app php artisan test            # 188 backend tests
docker compose exec app vendor/bin/phpstan analyse  # static analysis, 0 errors expected
docker compose exec app vendor/bin/pint --test      # code style check
docker compose exec node npm run build              # frontend type-check + production build
```

---

## 2. Login Credentials

All demo accounts share the password **`password`** (Laravel's standard factory default — this is
local/dev-only data, never used in a real deployment).

| Role | Email | Password | What they can do |
|---|---|---|---|
| Admin | `admin@example.com` | `password` | Full access — manage users, patients, (soon) appointments/types/schedules |
| Dentist | `dentist@example.com` | `password` | Read-only on Patients/Users; will get appointment/schedule actions once the Appointments frontend ships |
| Receptionist | `receptionist@example.com` | `password` | Manage patients; will get appointment booking once the Appointments frontend ships |

Seeded by `backend/database/seeders/DatabaseSeeder.php` — re-running `migrate:fresh --seed` always
recreates exactly these three accounts with these exact emails.

---

## 3. Available Screens

| Screen | Route | Who sees it |
|---|---|---|
| Login | `/login` | Everyone (unauthenticated) |
| Dashboard | `/` | All roles — patient count, today's appointments (currently always 0 — Appointments module pending), monthly revenue placeholder |
| Users | `/users` | All roles can view; only Admin can create/edit/delete |
| Patients | `/patients` | All roles can view/search; Admin + Receptionist can create/edit; Admin only can delete |
| Patient Details | `/patients/:id` | All roles; includes an audit-log panel visible to Admin only |

Not yet in the UI (backend complete, frontend pending — this is exactly what Phase 2 of the Appointments
module builds next): Appointments calendar/board, Appointment Types management, Dentist Working Hours,
Dentist Time Off. See `docs/modules/appointments-ui-design.md` for the full approved design.

---

## 4. Recommended Demo Flow

A ~5-minute walkthrough that touches every working piece and both roles' permission boundaries:

1. **Login** — open http://localhost:5173, sign in as `admin@example.com` / `password`.
2. **Dashboard** — observe the patient count card (should read 8 with the seeded demo data) and the
   locale switcher / dark-mode toggle in the header (try switching to Arabic — the whole layout flips RTL).
3. **Users** — navigate to Users, note all 3 demo accounts are listed with their roles. Try creating a new
   user (admin-only action) to show the create dialog + validation.
4. **Patients** — navigate to Patients, search for a patient by name (search-as-you-type, case-insensitive
   even with mixed-case input), open one patient's detail page.
5. **Patient Details** — show the demographic/clinical fields, and (as admin) the audit-log panel at the
   bottom — edit the patient's phone number and reload the panel to show the change was logged.
6. **Role boundaries** — log out, log back in as `dentist@example.com` / `password`. Show that Patients is
   still visible (read access) but the create/edit/delete buttons are gone. Confirm the Users page behaves
   the same way (view-only for a dentist).
7. **Receptionist role** — log in as `receptionist@example.com` / `password`, show they *can* create/edit
   patients but the Users page stays view-only (patient front-desk work vs. account management is a
   deliberate permission split — see `docs/modules/patients.md`/`docs/modules/users.md`).

---

## 5. Sample Data Available

Seeded by `php artisan migrate:fresh --seed` (`DatabaseSeeder` → `AppointmentTypeSeeder`):

**Users (3)** — see §2 for credentials.

**Patients (8)** — realistic fake demographic data via `PatientFactory`, sequential patient codes:

```
P-00001 - Zoey Jaskolski
P-00002 - Jannie Jacobs
P-00003 - Lelah Powlowski
P-00004 - Vernice Ratke
P-00005 - Shad Collier
P-00006 - Magdalena Powlowski
P-00007 - Alanna Mayert
P-00008 - Velva Strosin
```

(Exact names differ on every fresh `migrate:fresh --seed`, since `PatientFactory` generates them randomly —
only the P-00001..P-00008 codes and count are stable.)

**Appointment Types (6)** — fixed, named values (not random), matching real dental-practice categories, seeded via `firstOrCreate` so re-running the seeder never duplicates them:

| Name | Duration | Color |
|---|---|---|
| Consultation | 30 min | `#3B82F6` (blue) |
| Cleaning | 45 min | `#10B981` (green) |
| Filling | 45 min | `#F59E0B` (amber) |
| Root Canal | 90 min | `#EF4444` (red) |
| Crown | 60 min | `#8B5CF6` (purple) |
| Extraction | 30 min | `#F97316` (orange) |

All active (`is_active: true`) — this closes the gap tracked in `TECH_DEBT.md` where `GET
/api/appointment-types` previously returned an empty list on a fresh install.

**Appointments, Working Hours, Time Off**: none seeded yet — these become meaningful once the Appointments
frontend (Phase 2) exists to book against them. The backend API already supports all of this (see
`docs/modules/appointments-ui-design.md` §11 for the full endpoint list) if you want to exercise it directly
via `curl` or Postman before the UI exists.

---

## 6. Visual Review Method (no browser automation available to the assistant)

The assistant environment used to build this project has no browser/screenshot tooling — verification is
done via direct API calls (see the system validation checkpoint report from 2026-07-16). To actually *see*
the app, open it yourself:

1. Confirm the stack is up: `docker compose ps` — expect 5 containers, all `Up`.
2. Open **http://localhost:5173** in any browser.
3. You should land on the **Login** screen. Sign in with any credential set from §2.
4. You'll be redirected to the **Dashboard** (`/`).
5. Use the top navigation bar to reach **Users** (`/users`) and **Patients** (`/patients`).
6. Click any row in the Patients table to reach **Patient Details** (`/patients/:id`).

If port 5173 or 8000 don't respond, check `docker compose logs -f node` / `docker compose logs -f nginx`
respectively — the most common cause is the containers still being mid-startup (Vite's first cold start can
take under a minute).
