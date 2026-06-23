# Prospect registered company — Design Spec

**Date:** 2026-06-23  
**Status:** Approved  
**Scope:** Allow operators to register legal company details on a prospect for Companies House lookup when the business trades under a different name.

**Approach:** Dedicated columns on `prospects` (Approach A from brainstorming).

---

## Goal

`CompaniesHouseLookupService` searches Companies House using the prospect's `business_name` from Google Business Profile. When a practice trades under a different name than its legal entity, automatic matching fails or returns weak results. Operators need to register the correct legal name or company number so Companies House checks use the right entity.

---

## Decisions

| Topic | Decision |
|-------|----------|
| Data capture | Registered company name and/or number; number takes priority for lookup |
| Storage | Dedicated columns on `prospects` (mirrors `validator_override_*` pattern) |
| Lookup priority | Registered number → registered name → `business_name` fallback |
| Auto-recheck on save | Only when `companies_house_checked_at` is null (first registration) |
| Update registration | Save only; operator must click Recheck manually |
| UI location | Inline form inside existing Companies House card on prospect show page |
| Audit trail | Who registered, when, optional note; who cleared, when |
| On clear | Null active registration fields; set cleared audit fields; keep existing `companies_house_*` results |
| Re-register after clear | Reset cleared audit fields; treat as new registration for auto-recheck rule |
| Pipeline integration | No job changes — `CheckCompaniesHouseJob` picks up registered details automatically |

---

## Architecture

```
CompaniesHouseControl.jsx
        │
        ▼
RegisteredCompanyController     ← POST save / DELETE clear
        │
        ▼
prospects.registered_company_* columns
        │
        ▼
CompaniesHouseLookupService   ← resolution order: number → name → business_name
        │
        ▼
companies_house_* result columns (unchanged)
```

### New components

| Component | Responsibility |
|-----------|----------------|
| Migration | Add `registered_company_*` columns to `prospects` |
| `RegisteredCompanyController` | Save and clear registered company details |
| `StoreRegisteredCompanyRequest` | Validate name/number/note input |
| `CompaniesHouseLookupService` (modified) | Use registered details in resolution order |
| `CompaniesHouseControl.jsx` (modified) | Registration form, display, clear flow |
| `ProspectShowResource` (modified) | Expose registered company fields to frontend |

---

## Data model

### New columns on `prospects`

| Column | Type | Purpose |
|--------|------|---------|
| `registered_company_name` | string, nullable | Legal/registered name for CH name search |
| `registered_company_number` | string, nullable | Direct CH lookup (takes priority) |
| `registered_company_note` | text, nullable | Operator context (e.g. "from website footer") |
| `registered_company_by` | FK → users, nullable | Who registered |
| `registered_company_at` | timestamp, nullable | When registered |
| `registered_company_cleared_by` | FK → users, nullable | Who cleared |
| `registered_company_cleared_at` | timestamp, nullable | When cleared |

### Rules

- At least one of `registered_company_name` or `registered_company_number` required to save
- `registered_company_number` validated as 8 alphanumeric characters (standard Companies House format)
- Clearing nulls name, number, note, by, and at; sets cleared_by and cleared_at
- Re-registration after clear resets cleared_by and cleared_at to null
- Existing `companies_house_*` columns remain lookup **results** — never written by registration input

---

## Lookup service changes

`CompaniesHouseLookupService::check()` resolution order:

1. **`registered_company_number` set** — fetch profile directly by number (skip fuzzy search and match threshold)
2. **`registered_company_name` set** — search Companies House using registered name (not `business_name`)
3. **Else** — existing behaviour: search by `business_name`

### Registered company number

- Call profile endpoint directly; no name search
- Number not found → `caution` status, flag "Registered company number not found on Companies House"
- On success → same `assess()` logic as today

### Registered company name

- Same fuzzy matching as today, but search query is `registered_company_name`
- Postcode from prospect `address` still used for match scoring
- Summary prefixed with "Matched via registered company name"

### On clear

- Registered fields nulled; `companies_house_*` results kept
- UI shows "Registration cleared by X on Y" when cleared_at is set and no active registration

---

## API

| Method | Route | Action |
|--------|-------|--------|
| `POST` | `/prospects/{prospect}/registered-company` | Save registration |
| `DELETE` | `/prospects/{prospect}/registered-company` | Clear registration |

### Save (`POST`)

- Validates at least one of name/number; number format if provided
- Sets `registered_company_by` and `registered_company_at` to current user/time
- Clears `registered_company_cleared_by` and `registered_company_cleared_at` on re-registration
- If `companies_house_checked_at` is null → dispatch `CheckCompaniesHouseJob`
- Returns 202 if job queued, 200 if save only

### Clear (`DELETE`)

- Nulls name, number, note, by, at
- Sets `registered_company_cleared_by` and `registered_company_cleared_at`
- Does not clear `companies_house_*` results
- Does not auto-recheck
- Idempotent when no active registration

### Authorization

Same as existing prospect view (`view` policy).

---

## UI — Companies House card

Three states inside `CompaniesHouseControl.jsx`:

### 1. No registration

Collapsed form: name field, number field (at least one required to save), note field, Save button.

### 2. Active registration

Shows registered name/number, note, "Registered by X on Y". Edit and Clear buttons. Clear requires confirmation.

### 3. Cleared

When `registered_company_cleared_at` is set and no active registration: muted notice "Registration cleared by X on Y". Form available to register again.

Check status, summary, flags, and Recheck button remain below the registration section (unchanged).

---

## Error handling

| Scenario | Behaviour |
|----------|-----------|
| Save with neither name nor number | 422 validation error |
| Invalid company number format | 422 — company number must be 8 characters |
| Registered number not found on CH | Lookup completes with `caution` status + flag; registration kept |
| Registered name — no confident match | `no_match` status; registration kept for retry |
| CH API key missing | Existing behaviour — caution + flag; registration still saved |
| Clear with no active registration | Idempotent delete (204) |

---

## Testing

### Unit — `CompaniesHouseLookupService`

- Uses registered number directly (skips search)
- Uses registered name instead of `business_name`
- Falls back to `business_name` when no registration
- Invalid registered number → caution result
- Registered name match summary includes "Matched via registered company name"

### Feature

- Operator can save registration (name only, number only, both)
- First save with no prior check → `CheckCompaniesHouseJob` dispatched
- Update registration → no auto-recheck
- Clear registration → audit fields set, active fields nulled
- Re-register after clear → cleared fields reset; auto-recheck if never checked before

### Frontend

Manual verification on prospect show page: form, save, clear, recheck flow.

---

## Out of scope

- Registered company address (postcode from prospect `address` is sufficient for name matching)
- Version history of registration changes (single active registration + clear audit only)
- Bulk registration across prospects
- Auto-suggesting company names from website scraping
