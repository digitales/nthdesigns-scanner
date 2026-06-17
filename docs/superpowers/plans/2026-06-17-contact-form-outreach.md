# Contact form & LinkedIn outreach — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Support contact-form and LinkedIn outreach for prospects without public email addresses, with auto-detection during site audits and separate queue cards.

**Architecture:** Unified `outreach_emails.channel` column; `OutreachChannelResolver` routes email vs form+LinkedIn; `contact-detect.js` runs alongside CMS detection in Playwright; AI form messages + templated LinkedIn DMs.

**Tech Stack:** Laravel 13, Playwright/Node, Inertia/React, PHPUnit, OpenRouter.

**Spec:** `docs/superpowers/specs/2026-06-17-contact-form-outreach-design.md`

---

## Status: Implemented

All tasks from the spec are complete in this branch.

### Key files

| Area | Files |
|------|-------|
| Detection | `scripts/contact-detect.js`, `scripts/audit.js`, `scripts/detect-cms.js` |
| Routing | `app/Services/Outreach/OutreachChannelResolver.php` |
| Generation | `app/Jobs/GenerateOutreachEmailJob.php`, form/LinkedIn generator services |
| UI | `OutreachChannelCard.jsx`, `Outreach/Index.jsx`, `Prospect/Show.jsx` |
| Migrations | `2026_06_17_100000_*`, `2026_06_17_100001_*` |

### Verification

```bash
php artisan migrate
node --test scripts/contact-detect.test.js
php artisan test --filter='OutreachChannelResolverTest|OutreachLinkedInTemplateTest|GenerateOutreachEmailJobTest|DetectCmsJobTest|OutreachQueueLoaderTest|ProspectUnsubscribeTest'
npm run build
```
