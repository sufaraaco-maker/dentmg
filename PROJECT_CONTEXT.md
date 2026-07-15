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

Completed modules: Dashboard, Authentication (Sanctum SPA cookie auth; users/sessions tables use UUID primary keys), Users (CRUD + search, soft deletes, self-delete blocked), Roles & Permissions (simple backed enum: admin/dentist/receptionist; user management restricted to admin), Patients (standard clinical intake, patient_code, admin/receptionist write access, dentist read-only, generic audit log infrastructure — see docs/modules/patients.md).
Next module: Appointments.

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
