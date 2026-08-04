# DentalSuite — Project Instructions (Operational Playbook)

This file is the **Operational Playbook**: it sets the rules of engagement for working in this repository.
[`docs/PROJECT_STATUS.md`](docs/PROJECT_STATUS.md) is the **Living Engineering Status Book**: it reflects
the project's actual current state. The two work together — this file says *how* to work, that file says
*where things stand* — and neither is complete without the other. Read both before starting any task.

## Standing rules

1. **If `docs/PROJECT_STATUS.md` is out of date, updating it takes priority over any new development.**
   Don't start new feature work on top of a stale status file — close the gap first.
2. **No task is complete until every required piece of documentation is in sync with the code.** A correct
   code change with stale docs is an unfinished task, not a finished one with follow-up cleanup.
3. **Updating documentation is part of Definition of Done — never optional, never deferred "for later."**
   If a task changed status, architecture, scope, or known issues, the corresponding docs update is part of
   that same task, not a separate one.
4. **Keeping code and documentation consistent is a continuous responsibility for the entire life of this
   project** — not a one-time setup step, not something that lapses under time pressure or high velocity.
   `docs/PROJECT_STATUS.md` itself documents a real case (2026-08-02) where this slipped under a burst of
   fast-landing PRs — see that file's §0 and §14. Don't repeat it.

## Before starting any new task

1. Read `docs/PROJECT_STATUS.md` in full.
2. Review the latest commits, Pull Requests, and CI status (`git log`, `gh pr list --state all --limit 10`,
   `gh run list --branch main --limit 5`).
3. If you find any change not yet reflected in the docs, **update the documentation first**, then start the
   requested task — never the other way around.

## After finishing any task

Confirm every affected piece of documentation is updated before reporting the task done:

- `docs/PROJECT_STATUS.md` (always check — see its own §15.2 for the full trigger list: module status
  changes, PR merges, architecture/DB decisions, roadmap changes, UI changes, feature add/remove, bugs
  found/fixed, tech debt found/resolved, phase ends, CI/production-readiness shifts).
- `CHANGELOG.md`, if the change is the kind that file tracks.
- `TECH_DEBT.md`, if a debt item was created or resolved.
- `docs/decisions.md`, if a new architectural decision was made.
- The relevant design/module doc under `docs/modules/`, if it's now inconsistent with what was implemented.

## Avoid duplication

Don't repeat the same information across multiple files. When something belongs in `CHANGELOG.md`,
`TECH_DEBT.md`, `docs/decisions.md`, or `docs/roadmap.md`, record it there in full — then add only a short
summary plus a reference back to it in `docs/PROJECT_STATUS.md`. Treat `docs/PROJECT_STATUS.md` as the
**main entry point** into the rest of the documentation, not a second copy of it.

## Answering status questions

When asked (in any phrasing, Arabic or English) for a project report, completion percentage, what's been
accomplished, or what's next: `docs/PROJECT_STATUS.md` must already be current, or be updated first, before
answering — per the "Before starting any new task" steps above. Never answer from memory of an earlier
session without that verify-and-update pass.

---

`PROJECT_CONTEXT.md` remains the reference for architecture/stack/philosophy (rarely changes).
`docs/PROJECT_STATUS.md` is the reference for current status, history, technical debt, and what's next.
