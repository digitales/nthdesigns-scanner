# Warmup monitoring & outreach integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Notify operators when warmup mailboxes hit Ready, At Risk, or Connection Failed; show outreach readiness on `/outreach`; surface at-risk DNS guidance on mailbox detail.

**Architecture:** `WarmupNotifierService` owns alert + notification creation; `WarmupOutreachReadinessService` feeds outreach UI; shared `SkipBanner` + extracted React components.

**Tech Stack:** Laravel 13 notifications (database), Inertia + React, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-06-18-warmup-phase-7b-design.md`

---

## Status: Implemented

All tasks from the spec are complete (June 2026).

### Key files

| Area | Files |
|------|-------|
| Notifications | `app/Notifications/WarmupMailboxNotification.php`, `app/Services/Warmup/WarmupNotifierService.php`, `app/Http/Controllers/NotificationController.php`, `database/migrations/*_create_notifications_table.php` |
| Outreach readiness | `app/Services/Warmup/WarmupOutreachReadinessService.php`, `app/Http/Controllers/OutreachController.php` |
| Jobs | `app/Jobs/WarmupHealthCheckJob.php`, `app/Jobs/SendWarmupEmailJob.php`, `app/Jobs/ProcessWarmupInboxJob.php` |
| Shared props | `app/Http/Middleware/HandleInertiaRequests.php` |
| UI kit | `resources/js/Components/ui/SkipBanner.jsx`, `NotificationBell.jsx`, `resources/js/hooks/useDismissiblePopover.js` |
| Page components | `resources/js/Pages/Warmup/components/WarmupReadinessBanner.jsx`, `WarmupAlertBanners.jsx` |
| Pages | `resources/js/Pages/Outreach/Index.jsx`, `resources/js/Pages/Warmup/Show.jsx`, `resources/js/Components/ui/AppShell.jsx` |
| Styles | `resources/css/components.css` (notification bell, `skip-banner--critical`, `link-inline`) |
| Routes | `routes/web.php` (`notifications.read`, `notifications.read-all`) |

### Verification

```bash
php artisan migrate
php artisan test --filter='WarmupOutreachReadiness|WarmupNotifier|WarmupHealthCheck|OutreachIndex|NotificationController|Warmup'
npm run build
```

### Deferred

- Score trend chart (daily `warmup_deliverability_snapshots` + recharts)
- Agency+ multi-mailbox domain selector on `/outreach`
- Email notification delivery for Agency+ tier
