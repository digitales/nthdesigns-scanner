# CMS detection for audited sites — Design Spec

**Date:** 2026-06-01  
**Status:** Implemented  
**Scope:** Detect CMS/platform (WordPress priority + common SMB builders) for every prospect with a `website_url`; store detailed results with version, confidence, and signal trace; surface on operator UI only.

**Approach:** Shared Node detection module invoked from `audit.js` during full site audits, plus `DetectCmsJob` + `scripts/detect-cms.js` when no accessibility audit runs (`gbp_only`, etc.). Persist on `prospects.cms_detection` JSON; shape via `ReportBuilderService` for prospect detail and search results table.

---

## Goal

Operators prospecting local businesses need to know what CMS or site builder powers each website — especially **WordPress**, which signals a different sales and delivery angle. Detection should run automatically whenever a website URL is known, not only when axe/Lighthouse audits run. Results must be **detailed when possible** (platform, version, confidence, which heuristics matched) and visible on **operator** surfaces (search table, prospect detail), not on the shareable public report (`/r/{token}`).

---

## Decisions (brainstorming)

| Topic | Decision |
|-------|----------|
| Detail level | Platform + optional version + `high` / `medium` / `low` confidence + per-signal audit trail |
| Platforms (v1) | WordPress (priority), Shopify, Wix, Squarespace, Webflow, Joomla, Drupal; else `unknown` |
| When to detect | Any prospect with `website_url`, including `gbp_only` (no full site audit required) |
| Full audit path | Run detection in `audit.js` on the same Playwright navigation as axe (no second page load) |
| GBP-only / no audit | Queue `DetectCmsJob` → `scripts/detect-cms.js` (Playwright, DOM + headers only) |
| Public report | **No CMS** on `ReportBuilderService::build()` / `Report/Public.jsx` |
| Operator UI | Search results table badge + prospect detail “Technology” block |
| Search filters | Out of scope v1 (no “WordPress only” filter) |
| Outreach emails | Out of scope v1 |
| Scoring | CMS does not affect `combined_score`, flags, or pitch angle |

---

## Detection contract

Persisted JSON on `prospects.cms_detection`:

```json
{
  "platform": "wordpress",
  "version": "6.4.2",
  "confidence": "high",
  "signals": [
    { "id": "meta_generator", "matched": true, "detail": "WordPress 6.4.2" },
    { "id": "body_class_wp", "matched": true, "detail": "wp-singular page-id-12" },
    { "id": "html_wp_content", "matched": true, "detail": "/wp-content/themes/..." }
  ],
  "detected_at": "2026-06-01T12:00:00+00:00",
  "url": "https://example.com"
}
```

| Field | Type | Notes |
|-------|------|-------|
| `platform` | enum string | `wordpress`, `shopify`, `wix`, `squarespace`, `webflow`, `joomla`, `drupal`, `unknown` |
| `version` | string \| null | Primarily from `<meta name="generator">`; omit if not parseable |
| `confidence` | enum | `high`, `medium`, `low` — derived from weighted signal score |
| `signals` | array | All rules evaluated; `matched: true/false` with short `detail` |
| `detected_at` | ISO 8601 | UTC |
| `url` | string | Final URL after redirects (from Playwright) |

### Signal sources (single page load)

1. **HTTP response headers** — e.g. `x-powered-by`, `server` (weak; recorded but low weight).
2. **HTML `<head>`** — `<meta name="generator" content="...">`, `<link>` hrefs (`wp-content`, `wp-json`, `cdn.shopify.com`, etc.).
3. **`<body class>`** — token scan for platform-specific patterns (`wp-*`, `page-id-*`, Shopify/Wix markers where present).

Detection runs after `domcontentloaded` (same as current audit). No extra network requests beyond the initial navigation.

### Platform heuristics (v1)

**WordPress (highest priority)**

| Signal id | Match | Weight |
|-----------|-------|--------|
| `meta_generator` | `generator` contains `WordPress` | Strong |
| `body_class_wp` | body class matches `\bwp-` or `page-id-\d+` | Strong |
| `html_wp_content` | HTML contains `/wp-content/` or `/wp-includes/` or `wp-json` | Medium |
| `header_x_powered_by` | header mentions PHP/WordPress | Weak |

Version: parse from generator, e.g. `WordPress 6.4.2` → `6.4.2`.

**Other platforms (best-effort)**

| Platform | Example signals |
|----------|-----------------|
| Shopify | `cdn.shopify.com`, `Shopify.shop`, `myshopify.com` in HTML |
| Wix | `wix.com`, `wixstatic.com`, `wix-warmup-data`, `parastorage.com`, `wix-thunderbolt`, `wix-first-paint`; `id="wix-*"`; headers `x-wix-request-id`, `link` (wix/parastorage), `server: Pepyaka`; generator contains Wix |
| Squarespace | `squarespace.com`, `static.squarespace.com` |
| Webflow | `webflow.com`, `wfdesign`, `data-wf-page` |
| Joomla | generator `Joomla!`, `/components/com_` |
| Drupal | generator `Drupal`, `drupal.js`, `/sites/default/` |

If multiple platforms score above threshold, choose the highest total weight; include non-winning positive signals in `signals` with `matched: true` and note conflict in resolver (no separate field in v1).

**Failure / inconclusive**

- Navigation error, timeout, or empty body → `platform: unknown`, `confidence: low`, signal `fetch_failed` with error message.
- No platform rules matched → `platform: unknown`, `confidence: low`.

### Confidence thresholds

| Level | Rule (illustrative) |
|-------|---------------------|
| `high` | WordPress: generator **or** (body class + HTML path); other platforms: ≥2 strong signals |
| `medium` | One strong signal, or multiple weak signals for same platform |
| `low` | Only weak signals, or `unknown` |

Exact weights live in `scripts/cms-detect.js` (single source of truth). PHP display layer does not re-score.

---

## Architecture

### New / changed components

| Component | Responsibility |
|-----------|----------------|
| `scripts/cms-detect.js` | Export `detectCms(page, response)` → contract object; platform rules + scoring |
| `scripts/detect-cms.js` | CLI: URL arg → stdout JSON (Playwright goto only) |
| `scripts/audit.js` | After `page.goto`, call `detectCms`; include `cms` key in audit stdout JSON |
| `DetectCmsJob` | Run detect script; update `prospects.cms_detection`; use `AuditingQueue` |
| `CmsDetectionRunnerService` (or extend `AuditRunnerService`) | Invoke `detect-cms.js` via Process / HTTP browser service (mirror audit driver) |
| Migration | `cms_detection` nullable JSON on `prospects` |
| `Prospect` model | Cast `cms_detection` → array; fillable |
| `ReportBuilderService::cmsForProspect()` | Operator display shape: label, badge key, confidence, signals |
| `AuditSiteJob` | Persist `cms_detection` from audit payload `cms` (authoritative for audited prospects) |
| `ScorePlaceJob::dispatchNextStep` | If `website_url` and no `AuditSiteJob`, dispatch `DetectCmsJob` |
| `ProspectEnrichmentService` | On URL change: null `cms_detection`; dispatch `DetectCmsJob` when URL present; existing audit reset unchanged |
| `ProspectAuditService::auditResetFields` | Include `cms_detection` => null (re-filled when audit completes) |
| `ProspectListResource` | `cms_platform`, `cms_label` for table |
| `Search/Show.jsx` | CMS badge column |
| `Prospect/Show.jsx` | Technology block |

### Queue / job flow

```text
ScorePlaceJob
  ├─ website + a11y scan type → AuditSiteJob
  │     └─ audit.js → cms in payload → AuditSiteJob saves cms_detection
  └─ website + gbp_only → DetectCmsJob → cms_detection

ProspectEnrichmentService (URL changed)
  ├─ cms_detection cleared
  ├─ DetectCmsJob (if URL)
  └─ AuditSiteJob (if scan type requires a11y)
```

`DetectCmsJob` is idempotent: skip if `cms_detection` already set for current normalized URL unless `force` option (not required v1; URL change clears first).

### Browser service (Fly / HTTP audit driver)

- `audit.js` path: browser service `/audit` response must include `cms` object (update Fly handler if it strips unknown keys).
- `detect-cms.js` path: add `POST /detect-cms` or reuse a minimal endpoint; mirror `AuditRunnerService` driver switch (`playwright` local vs `http` remote).

If remote service changes are deferred, local `playwright` driver must still work in tests; document Fly deploy step in implementation plan.

---

## Data model

### `prospects.cms_detection`

```
cms_detection   json nullable
```

- Source of truth; not embedded in `raw_a11y_payload` for reads (audit payload may include `cms` for debugging only).
- Cleared when `website_url` changes (enrichment) or `auditResetFields()` (re-audit).
- Not purged separately from prospect expiry (follows prospect row).

### Display shape (operator API)

`ReportBuilderService::cmsForProspect(Prospect $prospect): ?array`

Returns `null` when no `website_url`.

Otherwise:

```php
[
    'platform' => 'wordpress',
    'version' => '6.4.2',
    'label' => 'WordPress 6.4',      // human label for UI
    'badge' => 'WP',                  // short table badge
    'confidence' => 'high',
    'signals' => [...],               // full trace for detail expandable
    'detected_at' => '...',
    'pending' => false,               // true when URL set but cms_detection null
]
```

Badge map: `WP`, `Shopify`, `Wix`, `Squarespace`, `Webflow`, `Joomla`, `Drupal`, `—` / `?` for unknown/pending.

---

## UX

### Search results (`/searches/{id}`)

- Add compact **CMS** badge near website column (after URL or in flags area).
- States: platform badge, `…` while pending (`website_url` set, `cms_detection` null), em dash when no website.

### Prospect detail (`/prospects/{id}`)

- **Technology** card or sidebar row (not inside public-report sections).
- Shows `label`, confidence chip (`High` / `Medium` / `Low`).
- Collapsible **Detection signals** list for operator debugging.
- Visible for `gbp_only` without site audit section.
- While pending: “Detecting platform…”

### Out of scope (v1)

- CMS on `/r/{token}` public report
- Search table filter/sort by CMS
- Outreach prompt / email body mentions
- Combined score or flag changes
- Backfill command (optional follow-up; mention in implementation plan only)

---

## Error handling

| Case | Behaviour |
|------|-----------|
| `DetectCmsJob` fails | Log error; leave `cms_detection` null or last value — prefer null after URL change; retry via job `tries` (2) |
| Audit succeeds but CMS block missing | Do not clear existing `cms_detection` unless audit payload includes `cms` |
| Audit fails | Do not update `cms_detection` from failed audit; prior detection remains until URL change or successful re-audit |
| `AUDIT_DRIVER=skip` | `DetectCmsJob` still runs when URL present and no audit |

---

## Testing

| Test | Assert |
|------|--------|
| Unit: `cms-detect` fixtures | HTML/header/body fixtures resolve expected platform, version, confidence |
| Feature: `DetectCmsJob` | Prospect with URL gets `cms_detection` after job (Http::fake or fixture script output) |
| Feature: `AuditSiteJob` | Mock audit payload with `cms` → column set |
| Feature: enrichment URL change | `cms_detection` cleared; new job queued |
| Feature: `ProspectListResource` | Includes `cms_label` when detection present |
| Feature: public report build | Payload has no `cms` key |

Use checked-in HTML snippets under `tests/fixtures/cms/` for Node unit tests (or PHP if rules duplicated — prefer testing script output only).

---

## Implementation notes

1. **Normalize URL** before compare (same as enrichment: lowercase, trim trailing slash).
2. **WordPress body classes** — many themes use `wp-singular`, `home`, `blog`, `page-id-N`; require `wp-` prefix or `page-id-\d+` to reduce false positives.
3. **Security** — detection is outbound fetch only; no user-supplied HTML execution beyond Playwright render.
4. **Performance** — `detect-cms.js` timeout ≤ 60s; job timeout 90s; same queue as auditing acceptable.

---

## Related docs

- `docs/superpowers/specs/2026-05-27-prospect-site-audit-detail-design.md` — site audit operator UI
- `docs/superpowers/specs/2026-05-28-prospect-enrichment-design.md` — URL change → re-audit
- `scripts/audit.js` — existing Playwright entry point
