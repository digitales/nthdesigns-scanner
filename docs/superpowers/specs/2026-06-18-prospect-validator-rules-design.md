# Prospect validator rules — Design Spec

**Date:** 2026-06-18  
**Status:** Approved  
**Scope:** Externalise franchise signals from hardcoded constants; operator-managed global signals; per-prospect overrides; selective re-validation.

**Approach:** Config defaults + DB operator signals (Approach 1 from brainstorming).

---

## Goal

`ProspectValidatorService` uses hardcoded franchise signal patterns duplicated from the qualification AI prompt. Operators need to add patterns as validation runs reveal false negatives, override individual prospects, and re-validate affected records without code deploys.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Default signals | `config/prospect_validator.php` — version-controlled, deploy with code |
| Operator signals | `prospect_validation_signals` table — CRUD via settings UI |
| One-off overrides | `validator_override_*` columns on `prospects` |
| Match fields | `qualification_flags`, `business_name`, `website_url`, `qualification_summary` |
| Match semantics | Case-insensitive substring (`str_contains`) |
| Override precedence | Operator override checked first; skips all rule evaluation |
| Franchise match outcome | `LowChance` + structured `validator_flags` (`franchise_signal:{pattern}:{field}`) |
| Re-validation scope | Selective — SQL pre-filter on match fields + validator_flags; skip override prospects |
| Re-validation triggers | Add/activate/change/deactivate operator signal; manual artisan command for config changes |
| Qualification prompt sync | Deferred — expanded field matching catches most AI misses |
| Search table column | Out of scope v1 |

---

## Architecture

```
config/prospect_validator.php
        │
        ▼
ProspectValidationRulesService     ← merges config + active DB signals
        │
        ▼
ProspectValidationSignalMatcher    ← multi-field haystack matching
        │
        ▼
ProspectValidatorService           ← override → skip status → franchise → existing logic
```

### New components

| Component | Responsibility |
|-----------|----------------|
| `config/prospect_validator.php` | Default signals, thresholds, match fields |
| `ProspectValidationSignal` model | Operator-added global signals |
| `ProspectValidationRulesService` | Merged active signal list |
| `ProspectValidationSignalMatcher` | Multi-field substring matching |
| `ProspectValidationRevalidationService` | Prospect ID scope query for selective re-validation |
| `RevalidateProspectsForSignalJob` | Chunked dispatch of `ValidateProspectJob` |
| `RevalidateValidationPatternCommand` | `validation:revalidate-pattern {pattern}` |
| `ValidationRulesController` | Settings CRUD for operator signals |
| `ProspectValidatorController` | Re-validate, set/clear override |
| `ValidationControl.jsx` | Prospect detail validation card |
| `Settings/ValidationRules.jsx` | Settings page for signal management |

---

## Data model

### `prospect_validation_signals`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `pattern` | string | Unique, normalised lowercase |
| `label` | string | Human-readable name |
| `active` | boolean | Default true |
| `notes` | text, nullable | |
| `created_by` | FK users | |
| `timestamps` | | |

### Prospect override columns

| Column | Type |
|--------|------|
| `validator_override_status` | string nullable (`high_chance` / `low_chance`) |
| `validator_override_note` | text nullable |
| `validator_override_by` | FK users nullable |
| `validator_override_at` | timestamp nullable |

---

## Evaluation order

1. Operator override → return override status
2. `qualification_status === 'skip'` → LowChance
3. Franchise signal match (config + DB) → LowChance
4. Existing weakness/score/review-count logic unchanged

---

## UI

### Prospect detail — Validation card

- Status badge, summary, flags, ran-at timestamp
- Actions: Re-validate, Override high/low, Clear override, Add as global signal

### Settings — `/settings/validation-rules`

- CRUD table for operator signals
- Read-only list of built-in config signals

---

## Testing

- Unit: `ProspectValidationSignalMatcherTest`, `ProspectValidationRulesServiceTest`, `ProspectValidatorServiceTest`
- Feature: `ProspectValidationSignalTest`, `ProspectValidatorOverrideTest`

---

## Out of scope (v1)

- Validator column in search results
- Qualification prompt sync from operator signals
- Per-signal custom match fields
- Weighted scoring model
