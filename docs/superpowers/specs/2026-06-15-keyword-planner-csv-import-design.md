# Keyword Planner CSV import — Design Spec

**Date:** 2026-06-15  
**Status:** Approved  
**Scope:** Import Google Ads Keyword Planner CSV exports on search results to auto-set CPC benchmark and seed keywords.

**Approach:** Server-side import endpoint with `KeywordPlannerCsvImporter` service. Parse export, filter non-commercial terms for median CPC, store all bid-bearing keywords, save immediately to search + market default.

---

## Goal

Operators export keyword stats from Google Ads Keyword Planner and currently copy keywords line-by-line into the scanner and manually calculate the CPC median. This feature replaces that manual step with a one-click CSV upload on the search results page.

CPC remains optional and independent of the Places scan. Import is only available after a niche+city search completes (`/searches/{id}`), not on the new-search form.

---

## Decisions

| Topic | Decision |
|-------|----------|
| CPC calculation | Auto-calculate median of **top of page bid (high range)** on filtered commercial rows |
| Rounding | Nearest £0.50 (e.g. £6.38 → £6.50), matching `docs/cpc-benchmarks.md` |
| UI location | Search results only — `CpcBenchmarkPanel` on `/searches/{id}` |
| Row filter for median | Rows with high bid **minus** non-commercial keyword patterns |
| Seed keywords stored | **All** rows with a high-range bid (including non-commercial rows excluded from median) |
| Save behaviour | Immediate save to search + `market_cpc_defaults` (no preview/confirm step) |
| `cpc_source` | `keyword_planner_csv` |
| File format | Google Ads Keyword Planner export only (tab-separated, 2-line header) |
| Max file size | 2 MB |
| Google Ads API | Not used — manual export + import only |

---

## Architecture

```text
Operator uploads CSV on /searches/{id}
        ↓
POST /searches/{search}/cpc/import  (multipart)
        ↓
KeywordPlannerCsvImporter
  · detect Keyword Planner format
  · parse tab-separated rows (skip 2 header rows)
  · collect rows with "Top of page bid (high range)" > 0
  · split into:
      – all_keywords   → every row with high bid (stored in cpc_keywords)
      – commercial_bids → subset after non-commercial filter (median input)
  · median(commercial_bids) → round to nearest £0.50 → cpc_benchmark
        ↓
SearchController::importCpc (shared save with updateCpc)
  · cpc_source = keyword_planner_csv
  · upsert market_cpc_defaults
        ↓
Redirect back with flash:
  "Imported 38 keywords · CPC £6.50 (median of 12 commercial terms)"
```

### New components

| Component | Responsibility |
|-----------|----------------|
| `KeywordPlannerCsvImporter` | Parse CSV, validate format, filter, compute median + rounding |
| `KeywordPlannerImportResult` | Value object: `benchmark`, `keywords`, `commercialCount`, `totalCount` |
| `ImportSearchCpcRequest` | Validate uploaded file (required, file, mimes:csv,txt, max:2048) |
| `SearchController::importCpc` | Authorize, guard niche/city, call importer, persist, flash summary |
| Route `POST /searches/{search}/cpc/import` | Named `searches.cpc.import` |
| `CpcBenchmarkPanel` UI | Hidden file input + "Import from Keyword Planner" button |
| `tests/fixtures/keyword-planner-export.csv` | Trimmed real export subset for tests |

### Extended components

| Component | Change |
|-----------|--------|
| `SearchController::updateCpc` | Extract shared `persistCpc()` helper used by import and PATCH |
| `CpcBenchmarkPanel.jsx` | Import button, `keyword_planner_csv` source label |
| `Search/Show.jsx` | Wire import handler (FormData POST via router) |
| `docs/cpc-benchmarks.md` | Add import workflow subsection |

### Unchanged (reused)

- `MarketCpcDefaultService::upsert` — market default persistence
- `UpdateSearchCpcRequest` — manual PATCH path unchanged
- Outreach CPC resolution order — search CPC still used at generate time
- Google Ads API fetch button — remains dormant (`GOOGLE_ADS_ENABLED=false`)

---

## Parser details

### Format detection

1. Read file as UTF-8 (strip BOM if present).
2. Skip lines 1–2 (export title + date range).
3. Line 3 must be tab-separated header containing columns:
   - `Keyword`
   - `Top of page bid (high range)`
4. Reject if delimiter appears comma-only or required columns missing.

Column indices resolved from header names (not hard-coded positions).

### Per-row rules

- `keyword` = trimmed text from Keyword column; skip if empty.
- `high_bid` = float from high-range column; skip if empty, zero, or non-numeric.
- Rows passing `high_bid > 0` go into `all_keywords`.

### Non-commercial filter

Applied only when building `commercial_bids` for median calculation. Case-insensitive word-boundary match on keyword text:

`graduate`, `grad`, `internship`, `intern`, `career`, `careers`, `jobs`, `job`, `scheme`, `schemes`, `vacancy`, `vacancies`, `recruitment`, `hiring`, `apprentice`

Keywords matching any pattern are excluded from median but **included** in stored `cpc_keywords`.

### Median + rounding

```php
sort($commercial_bids);
$median = $commercial_bids[(int) floor((count($commercial_bids) - 1) / 2)];
$benchmark = round($median * 2) / 2;  // nearest £0.50
```

Uses same median index logic as `GoogleAdsKeywordPlanService`.

---

## UI

### Search results (`/searches/{id}`)

Add to `CpcBenchmarkPanel` action row:

- **Import from Keyword Planner** — ghost button, opens hidden `<input type="file" accept=".csv">`
- On file select → `POST /searches/{search}/cpc/import` with `FormData`
- Button disabled/shows **Importing…** during request

**Source badge:** `keyword_planner_csv` → label **Keyword Planner CSV**

**Textarea hint:** "One per line · from Keyword Planner export or manual entry"

### Out of scope (v1)

- Drag-and-drop upload zone
- Inline preview/confirmation table
- Import on `/search` (new search form)
- Other CSV formats (Search Terms report, bulk upload outside a search)

---

## Error handling

All errors redirect back with `flash.error`. No partial save.

| Condition | Message |
|-----------|---------|
| Missing/invalid file | "Please upload a CSV file from Keyword Planner (max 2 MB)." |
| Unrecognised format | "This doesn't look like a Keyword Planner export. Export from Discover new keywords in Google Ads." |
| No rows with high bids | "No keywords with bid data found in this file." |
| All rows filtered (no commercial) | "No commercial keywords with bid data found. Check your Keyword Planner seeds." |
| Search missing niche/city | "CPC import requires a niche and city search." |
| Unauthorized | 403 (existing policy) |

Success flash:

> Imported {totalCount} keywords · CPC £{benchmark} (median of {commercialCount} commercial terms)

---

## HTTP route

| Method | Path | Name | Purpose |
|--------|------|------|---------|
| `POST` | `/searches/{search}/cpc/import` | `searches.cpc.import` | Upload Keyword Planner CSV |

Request: `multipart/form-data` with field `file`.

Response: redirect back (Inertia-compatible).

---

## Testing

### Unit — `KeywordPlannerCsvImporterTest`

Uses `tests/fixtures/keyword-planner-export.csv` (trimmed subset of real export):

| Test | Asserts |
|------|---------|
| Parses valid export | Correct keyword and bid extraction |
| Skips empty-bid rows | Rows without high bid excluded |
| Non-commercial filter | e.g. `graduate consulting jobs` excluded from median, present in `all_keywords` |
| Median + rounding | Sample bids → median £6.38 → benchmark **£6.50** |
| Rejects bad format | Missing/wrong headers → error result |
| Rejects all-filtered | Every row non-commercial → error result |

### Feature — extend `SearchCpcTest`

| Test | Asserts |
|------|---------|
| Import saves search | `cpc_benchmark`, `cpc_keywords`, `cpc_source = keyword_planner_csv` |
| Import upserts market default | Matching niche/city/country row |
| Flash summary | Session contains keyword count and CPC |
| Forbidden for other user | 403 |
| Requires niche/city | Error flash on direct-URL search |

---

## Documentation

Update `docs/cpc-benchmarks.md`:

1. Add **Import from Keyword Planner** under Recommended workflow (step 4 on search results replaces manual median calc).
2. Note `cpc_source = keyword_planner_csv` in the stored-fields table.
3. Keep manual entry path documented — import is optional, not required.

---

## Example

Given rows from a consulting export:

| Keyword | High bid (£) | In median? |
|---------|-------------|------------|
| business consultant uk | 14.79 | Yes |
| business consultant london | 11.28 | Yes |
| graduate consulting jobs | 0.99 | No (non-commercial) |
| consulting careers | 1.93 | No (non-commercial) |

- `cpc_keywords`: all four keywords (any row with high bid)
- `commercial_bids`: [14.79, 11.28] → median 13.035 → **£13.00**
- Flash: "Imported 4 keywords · CPC £13.00 (median of 2 commercial terms)"
