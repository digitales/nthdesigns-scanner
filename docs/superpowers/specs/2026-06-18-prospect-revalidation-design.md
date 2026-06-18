# Prospect revalidation — Design Spec

**Date:** 2026-06-18  
**Status:** Approved  
**Scope:** Unified revalidation for existing prospects; conservative validate-first pipeline gate; force qualify; bulk backfill command.

**Approach:** Extend existing jobs (Approach 1 from brainstorming).

**Related:** Builds on [2026-06-18-prospect-validator-rules-design.md](./2026-06-18-prospect-validator-rules-design.md) (operator signals, pattern revalidation, manual re-validate UI — already shipped).

---

## Goal

Existing prospects created before `ProspectValidatorService` have null `validator_*` fields. New scans should avoid unnecessary AI qualification costs while still surfacing `HighChance` outreach candidates. Operators need unified revalidation across backfill, rule changes, manual triggers, and explicit force-qualify overrides.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Triggers | Backfill (bulk), pattern/signal changes (existing), manual re-validate (existing), force qualify (new) |
| Pipeline order | Validate first, then qualify only when not definitive `LowChance` |
| Gate policy | Conservative — skip qual only on definitive `LowChance`; never skip on `insufficient_qualification_data` alone |
| Backfill default | `ValidateProspectJob` only — no AI cost; prospects with `qualification_ran_at` set |
| Backfill force | `--force-qualify` dispatches `QualifyProspectJob` (qualify → re-validate), bypassing gate |
| Force qualify (UI) | "Force qualify" on validation card; bypasses 5-minute qual cooldown |
| Override prospects | Excluded from backfill scope; pattern revalidation already skips overrides |
| Estimated AI savings | ~30–50% on new scans (franchise name/URL + already-strong digital) |
| Missed HighChance risk | ~0% with conservative gate |

---

## Architecture

```
CombineScoresJob
    └── ValidateProspectJob (chainQualification: true)
            ├── definitive LowChance → stop
            └── else → QualifyProspectJob
                    └── ValidateProspectJob (chainQualification: false)

Backfill / manual re-validate / pattern revalidation
    └── ValidateProspectJob (chainQualification: false)

Force qualify (UI, backfill --force-qualify, existing qualify buttons)
    └── QualifyProspectJob
            └── ValidateProspectJob (chainQualification: false)
```

### Definitive LowChance (skip qualification)

`ProspectValidatorService::shouldSkipQualification()` returns `true` when `assess()` would produce:

| Condition | Flag / signal |
|-----------|---------------|
| Franchise/corporate signal match | `franchise_signal:{pattern}:{field}` |
| Already digitally strong | `already_digitally_strong` (`combined_score` < 25) |
| Confirmed corporate from prior qual | `corporate_or_franchise_confirmed` (`qualification_status === 'skip'`) |

### Not definitive (still qualify)

- `insufficient_qualification_data` alone
- `high_review_count` or `no_direct_contact` flags without a definitive outcome
- Any prospect that could become `HighChance` after AI qualification

---

## Components

### Modified

| Component | Change |
|-----------|--------|
| `ProspectValidatorService` | Add `shouldSkipQualification(Prospect): bool` using shared `assess()` logic |
| `ValidateProspectJob` | Add `chainQualification` (default `false`) and `forceQualification` (default `false`); chain to `QualifyProspectJob` when appropriate |
| `CombineScoresJob` | Dispatch `ValidateProspectJob($prospect, chainQualification: true)` instead of `QualifyProspectJob` |
| `ProspectController::qualify` | Accept `force` param; bypass 5-minute cooldown when `force=true` |
| `ValidationControl.jsx` | Add "Force qualify" button calling qualify endpoint with `force: true` |

### New

| Component | Responsibility |
|-----------|----------------|
| `BackfillValidationCommand` | `validation:backfill` — dry-run table + scoped dispatch |
| `ProspectValidationBackfillQuery` | Scope builder for unvalidated prospects (optional extract from command) |

### Unchanged (already shipped)

| Component | Responsibility |
|-----------|----------------|
| `ProspectValidationRevalidationService` | Pattern-scoped selective revalidation |
| `RevalidateValidationPatternCommand` | `validation:revalidate-pattern {pattern}` |
| `RevalidateProspectsForSignalJob` | Chunked dispatch on signal CRUD |
| `ProspectValidatorController` | Manual re-validate, override save/clear |
| `ValidationControl.jsx` | Re-validate, override, add global signal |

---

## Backfill command

```
php artisan validation:backfill
    {--execute : Dispatch jobs (default dry-run)}
    {--search= : Limit to search ID}
    {--prospect= : Limit to prospect ID}
    {--limit= : Maximum prospects per run}
    {--delay=200 : Milliseconds between dispatches}
    {--force-qualify : Dispatch QualifyProspectJob instead of ValidateProspectJob}
```

### Default scope

```sql
WHERE validator_ran_at IS NULL
  AND validator_override_status IS NULL
```

Optional filters: `--search=`, `--prospect=`, `--limit=`

### Behaviour

| Mode | Dispatches | AI cost |
|------|------------|---------|
| Default (dry-run) | None — prints table | $0 |
| `--execute` | `ValidateProspectJob` per prospect | $0 |
| `--execute --force-qualify` | `QualifyProspectJob` per prospect | ~$0.01/prospect with website |

Dry-run table columns: `prospect_id`, `business_name`, `search_id`, `qualification_status`, `combined_score`, `action` (`validate` or `qualify+validate`).

Follow `scanner:backfill-audits` conventions: `QueueDispatchDelay` cap warning, staggered dispatch, re-run until empty.

---

## Force qualify

Force qualify always runs `QualifyProspectJob` → `ValidateProspectJob`, ignoring the conservative gate.

| Entry point | Mechanism |
|-------------|-----------|
| Validation card | `POST /prospects/{prospect}/qualify` with `{ force: true }` |
| Search/saved lists | Existing per-row qualify buttons (unchanged; always forces a new qual run when not rate-limited) |
| Backfill | `--force-qualify` flag |

### Cooldown bypass

`ProspectController::qualify` currently returns 200 without re-queuing when `qualification_ran_at` is within 5 minutes. When `force=true`, skip the cooldown check and always dispatch `QualifyProspectJob`.

---

## Error handling

- Missing prospect in job handlers: return early (existing behaviour).
- Backfill with empty scope: info message, exit 0.
- `validation:revalidate-pattern` with empty pattern: exit 1 (existing).
- Force qualify on prospect without website: `ProspectQualificationService` sets `caution` without AI call (existing).

---

## Testing

### Unit

- `ProspectValidatorServiceTest`: `shouldSkipQualification` returns `true` for franchise signal, `already_digitally_strong`, `corporate_or_franchise_confirmed`
- `ProspectValidatorServiceTest`: returns `false` for `insufficient_qualification_data` only, for unqualified prospect with moderate weakness score

### Feature

- Pipeline: `CombineScoresJob` completion dispatches `ValidateProspectJob` with `chainQualification=true`; qualifies only when gate allows
- Pipeline: definitive `LowChance` prospect does not dispatch `QualifyProspectJob`
- `POST /prospects/{id}/qualify` with `force=true` bypasses 5-minute cooldown
- `validation:backfill` dry-run lists correct scope; `--execute` dispatches `ValidateProspectJob`
- `validation:backfill --force-qualify --execute` dispatches `QualifyProspectJob`
- Backfill excludes prospects with `validator_override_status` set

---

## Out of scope

- Validator column in search results table
- Aggressive gate (skip qual on `insufficient_qualification_data`)
- Automatic backfill on deploy (operator runs command manually)
- Re-qualify all prospects on validator rule changes (pattern revalidation handles signal changes only)
