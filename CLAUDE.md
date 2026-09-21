# bug-report

Personal bug-log site (public portfolio + LinkedIn-linked case log). Guiding principle: **keep it as simple as possible** — no over-engineering, no speculative features.

## Read first
- **[doc/PLAN.md](doc/PLAN.md)** — live progress checklist. Start here to see what's done and what's next.
- **[doc/SPEC.md](doc/SPEC.md)** — master design doc: purpose, stack, data model, security, visual design, all of it. The canonical spec.
- **[doc/TEST.md](doc/TEST.md)** — manual QA checklist, grows as phases land.

Read these before any implementation work; do not duplicate their content here.

## Status
Phases 0-6 done: DB, includes, public feed, case page, entry form, and admin listing/edit are all built and covered by `./test.sh`. Visual design (Phase 7) and security hardening (Phase 8) are next. Update this section as phases in PLAN.md complete.

## Maintenance
This file must stay small. New design decisions go into `doc/SPEC.md` as a new/updated section, not a new file. If a new doc is genuinely needed under `doc/`, add a one-line pointer under **Read first** instead of inlining its content.
