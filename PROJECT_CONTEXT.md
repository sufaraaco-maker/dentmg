# DentalSuite AI Context

Version: 1.0

---

# Project Overview

Project Name: DentalSuite

Goal: Build a modern, professional Dental Clinic Management System using Laravel 12 and Vue 3.

The project focuses ONLY on Dental Clinics.
It is NOT intended to become a Hospital Management System.

The philosophy is:

Simple
Fast
Modern
Scalable
Maintainable

The system should be enterprise-quality but without unnecessary complexity.

---

# Technology Stack

Backend

- Laravel 12
- PHP 8.4+

Frontend

- Vue 3
- TypeScript
- PrimeVue
- TailwindCSS

Database

- PostgreSQL

Cache

- Redis

Storage

- Local
- S3 Compatible

Deployment

- Docker

IDE

- Claude Code

---

# Supported Languages

Arabic (RTL)
English (LTR)
Turkish (LTR)

---

# Main Modules

Dashboard
Authentication
Users
Roles & Permissions
Patients
Appointments
Dental Chart
Treatment Plans
Clinical Notes
Billing
Payments
Inventory
Laboratory
Imaging
Reports
Settings
AI Assistant

---

# AI

Claude API integration is optional.
AI is an assistant only.

AI may help with:

Clinical Notes
Treatment Suggestions
Smart Search
Dashboard Insights
Writing Reports

Never allow AI to make medical decisions.

---

# Architecture

Modular Monolith
Clean Architecture
API First
Service Layer
Thin Controllers
Business Logic inside Services
Reusable Components

---

# Database

PostgreSQL
UUID
Soft Deletes
Audit Logs
Multi Branch
Single Organization (No Multi Tenant in Version 1)

---

# UI Principles

Simple
Modern
Responsive
Dark Mode
RTL Ready
Minimal Clicks
Excellent UX

---

# Coding Standards

PSR-12
Laravel Pint
PHPStan
Feature Tests
Unit Tests
No duplicated code
Readable code

---

# Project Philosophy

Keep it simple.
Do not over engineer.
Build only what is needed.
Always prefer readability.
Performance before complexity.
UX before fancy features.
AI is optional.

---

# Current Status

Implementation Phase.

The architecture has been approved.
The technology stack has been approved.
The initial blueprint has been approved.
Repository structure: Monorepo (backend/, frontend/, docker/).

Completed modules: Dashboard, Authentication (Sanctum SPA cookie auth; users/sessions tables use UUID primary keys), Users (CRUD + search, soft deletes, self-delete blocked), Roles & Permissions (simple backed enum: admin/dentist/receptionist; user management restricted to admin), Patients (standard clinical intake, patient_code, admin/receptionist write access, dentist read-only, generic audit log infrastructure — see docs/modules/patients.md), Appointments (Calendar Board with Day/Week/Month/List views, Appointment Types, Dentist Working Hours/Time Off, Dashboard widgets, keyboard shortcuts + full a11y/RTL/responsive pass — see docs/modules/appointments-ui-design.md and TECH_DEBT.md for open items).

System-Wide Production Gate (started 2026-07-18, per explicit user request, before starting the next module; closed 2026-07-20): DatabaseSeeder demo-account environment gate + `app:create-admin` command, general API rate limiting, production Docker/nginx/SSL topology (`docker-compose.prod.yml`), backup/restore scripts (rehearsed end-to-end, not just written — see TECH_DEBT.md), S3 offsite backup made config-only-activation-ready, CI/CD quality gate (`.github/workflows/ci.yml`) — Backend, Frontend, and E2E (permanent `frontend/e2e/` Playwright suite) jobs all **confirmed green on `main`** via the GitHub Actions API (run `29763458360`, commit `3faf2d7`: 13/13 E2E tests passed in 36.2s, alongside Backend/Frontend). See TECH_DEBT.md's CI entry for the full debugging trail — the last failure took four rounds of real root-causing (a DatePicker popover z-order issue, a non-retrying Playwright assertion, a guessed/non-deterministic time slot that self-collided across Playwright's automatic retry, a missing tab click on Edit, and a strict-mode-ambiguous post-cancel selector) before landing clean.

**Gate status: closed (2026-07-20)** — confirmed via the GitHub Actions API, not just local runs (a local Windows Docker Desktop networking pathology made this suite's last few percent unverifiable locally in isolation; CI's native runner has no such issue).

Next module: Dental Chart.

Full documentation set: see docs/ (architecture, database-design, api-guidelines, coding-standards, decisions, roadmap, deployment, modules/), plus CHANGELOG.md and TECH_DEBT.md at the repo root.

---

# Development Strategy

Implement one module at a time.
Complete every module before starting another.

Every module must include

Migration
Model
Validation
Service
Policy
API
Vue Pages
Tests
Documentation

---

# Claude Instructions

Always read this file first.
Never change architecture without asking.
Never introduce unnecessary packages.
Prefer Laravel native solutions.
Ask before making major decisions.
Explain tradeoffs when multiple solutions exist.
Always keep the project maintainable.
