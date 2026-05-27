# `niches:bootstrap` — Design Spec

**Date:** 2026-05-27  
**Status:** Approved — plan at `docs/superpowers/plans/2026-05-27-niches-bootstrap.md`  
**Scope:** One-time Artisan command to assemble initial UK city and niche lists and write `config/niches.php`. Ships with consumer updates so `niches:scan` never reads a broken config shape.

**Related:** [Niche Opportunity Scanner](2026-05-27-niche-opportunity-scanner-design.md) (runtime scanning; config shape updated by this spec).

**Approach:** Single command class (`NichesBootstrapCommand`) with private step methods, HTTP fallbacks, and synchronous Places validation against Birmingham only.

---

## Goal

Give operators a **one-time** way to bootstrap `config/niches.php` from:

1. ONS major UK settlements (+ devolved-nation supplement),
2. Google Places type taxonomy (filtered),
3. Optional Places API validation (Birmingham sample),
4. A hand-editable PHP config file consumed by `niches:scan`.

After bootstrap, config is **edited manually**. The command is not scheduled and does not create DB records.

---

## Decisions (brainstorming)

| Topic | Decision |
|-------|----------|
| Consumer updates | Same implementation as bootstrap — update `ScanNichesCommand` + tests |
| `--population` | **Dropped** — ONS endpoint used does not expose population; TCITY dataset treated as major settlements |
| Config layout | **One file:** `config/niches.php` with `niches` and `cities` keys |
| `primary_type` | Stored in each niche entry; **not** used by `searchByNicheAndCity` or `niches:scan` (metadata / future use) |
| Implementation shape | Single command class (no extracted service layer) |

---

## Command interface

**Class:** `App\Console\Commands\NichesBootstrapCommand`  
**Signature:** `niches:bootstrap`

```bash
php artisan niches:bootstrap {--min-results=5} {--dry-run}
```

| Option | Default | Behaviour |
|--------|---------|-----------|
| `--min-results` | `5` | Minimum `count(placeIds)` in Birmingham to keep a niche after validation |
| `--dry-run` | off | Run all steps; print would-be config; never write files |

**Dependencies:** Constructor-inject `GooglePlacesService`.

**External HTTP:** `Http::timeout(15)->get()` for ONS and taxonomy fetches.

**Top-level errors:** `try/catch` in `handle()` → `$this->error()`, return `Command::FAILURE`.

**Not in scope:** `--population`, DB writes, GBP scoring, audits, UI, queue jobs, scheduler entry.

---

## Pipeline overview

```text
Step 1: Fetch UK cities (ONS → supplement → sort)
Step 2: Fetch & filter Places types (HTML → blocklist/allowlist → labels)
Step 3: Validate niches (Birmingham × searchByNicheAndCity using query)
Step 4: Write config/niches.php (confirm overwrite; dry-run skips write)
Summary table
```

Progress logging: `$this->info()`, `$this->warn()`, `$this->withProgressBar()` in Step 3.

---

## Step 1: UK cities

### ONS fetch

```
GET https://services1.arcgis.com/ESMARspQHYMw9BZ9/arcgis/rest/services/Major_Towns_and_Cities_Dec_2015_Names_and_Codes_in_England_and_Wales_2022/FeatureServer/0/query
  ?where=1=1&outFields=TCITY15NM&returnGeometry=false&resultRecordCount=2000&f=json
```

Parse `features[].attributes.TCITY15NM` (112 major towns/cities in England and Wales). Treat JSON `error` payloads as fetch failure even when HTTP 200.

### Supplement (devolved nations)

Merge and dedupe:

```php
['Edinburgh', 'Glasgow', 'Aberdeen', 'Dundee', 'Inverness',
 'Cardiff', 'Swansea', 'Newport', 'Belfast', 'Derry']
```

Sort alphabetically → `$cities`.

### Failure handling

On HTTP/parse failure: use hardcoded fallback (30 cities), `$this->warn()`, continue.

```php
['Birmingham', 'Manchester', 'Leeds', 'Sheffield', 'Bradford',
 'Liverpool', 'Bristol', 'Coventry', 'Leicester', 'Nottingham',
 'Newcastle', 'Southampton', 'Brighton', 'Plymouth', 'Stoke-on-Trent',
 'Wolverhampton', 'Derby', 'Swansea', 'Norwich', 'Luton',
 'Edinburgh', 'Glasgow', 'Aberdeen', 'Cardiff', 'Belfast',
 'London', 'Oxford', 'Cambridge', 'Bath', 'Exeter']
```

### Log

`Found {n} UK settlements`

---

## Step 2: Places taxonomy → niche candidates

### Fetch

```
GET https://developers.google.com/maps/documentation/places/web-service/place-types
```

### Extract

Unique matches of `/\b[a-z][a-z_]{3,40}\b/` on response body.

### Filter

**Blocklist** — exclude type if any substring matches (case-sensitive on token):

```php
['transit', 'station', 'airport', 'parking', 'atm', 'bank',
 'finance', 'government', 'post_office', 'embassy', 'courthouse',
 'fire_station', 'police', 'prison', 'cemetery', 'funeral',
 'storage', 'moving', 'laundry', 'car_wash', 'car_repair',
 'gas_station', 'electric_vehicle', 'lodging', 'campground',
 'rv_park', 'grocery', 'supermarket', 'convenience', 'liquor',
 'hardware', 'home_goods', 'furniture', 'electronics', 'clothing',
 'shoe', 'jewelry', 'book_store', 'bicycle', 'department_store',
 'shopping_mall', 'wholesale', 'florist', 'gift', 'toy',
 'pet_store', 'aquarium', 'zoo', 'museum', 'art_gallery',
 'amusement', 'casino', 'movie', 'stadium', 'bowling',
 'night_club', 'bar', 'cafe', 'bakery', 'meal_takeaway']
```

**Allowlist signals** — keep if any substring matches; **allowlist wins over blocklist**:

```php
['doctor', 'dentist', 'health', 'medical', 'hospital', 'clinic',
 'lawyer', 'legal', 'accountant', 'finance_advisor', 'insurance',
 'real_estate', 'physiotherapist', 'veterinary', 'optician',
 'beauty', 'hair', 'spa', 'gym', 'fitness', 'plumber', 'electrician',
 'contractor', 'architect', 'tutor', 'school', 'consultant']
```

### Map to niche entries

For each surviving type `$t`:

- `primary_type` => `$t`
- `label` => `Str::title(str_replace('_', ' ', $t))`
- `query` => `str_replace('_', ' ', $t)` (lowercase words, spaces)

### Taxonomy failure fallback

```php
[
    ['label' => 'Dental Practice',     'query' => 'dental practice',     'primary_type' => 'dentist'],
    ['label' => 'Physiotherapist',     'query' => 'physiotherapist',     'primary_type' => 'physiotherapist'],
    ['label' => 'Solicitor',           'query' => 'solicitor',           'primary_type' => 'lawyer'],
    ['label' => 'Accountant',          'query' => 'accountant',          'primary_type' => 'accounting'],
    ['label' => 'Estate Agent',        'query' => 'estate agent',        'primary_type' => 'real_estate_agency'],
    ['label' => 'Independent Hotel',   'query' => 'independent hotel',   'primary_type' => 'lodging'],
    ['label' => 'Restaurant',          'query' => 'restaurant',          'primary_type' => 'restaurant'],
    ['label' => 'Optician',            'query' => 'optician',            'primary_type' => 'optician'],
    ['label' => 'Veterinary Practice', 'query' => 'vet practice',        'primary_type' => 'veterinary_care'],
    ['label' => 'Private GP',          'query' => 'private GP',          'primary_type' => 'doctor'],
    ['label' => 'Osteopath',           'query' => 'osteopath',           'primary_type' => 'physiotherapist'],
    ['label' => 'Chiropractor',        'query' => 'chiropractor',        'primary_type' => 'physiotherapist'],
    ['label' => 'Beauty Salon',        'query' => 'beauty salon',        'primary_type' => 'beauty_salon'],
    ['label' => 'Barbershop',          'query' => 'barbershop',          'primary_type' => 'hair_care'],
    ['label' => 'Plumber',             'query' => 'plumber',             'primary_type' => 'plumber'],
    ['label' => 'Electrician',         'query' => 'electrician',         'primary_type' => 'electrician'],
    ['label' => 'Architect',           'query' => 'architect',           'primary_type' => 'architect'],
    ['label' => 'Financial Adviser',   'query' => 'financial adviser',   'primary_type' => 'finance'],
    ['label' => 'Mortgage Broker',     'query' => 'mortgage broker',     'primary_type' => 'finance'],
    ['label' => 'Private Tutor',       'query' => 'private tutor',       'primary_type' => 'tutoring_center'],
]
```

### Log

`Extracted {n} candidate niches from Places taxonomy`

---

## Step 3: Validation (Places API)

**Representative city:** `Birmingham`  
**Search:** `GooglePlacesService::searchByNicheAndCity($niche['query'], 'Birmingham', 'GB')`  
**Metric:** `count($placeIds)`

### Skip validation when

`config('services.google_places.key')` is empty (equivalent to unset `GOOGLE_PLACES_API_KEY`):

- Warn once.
- Keep all Step 2 candidates.

### Per-niche loop

- `$this->withProgressBar()` over candidates.
- If `count < --min-results`: drop; `$this->warn()` with label + count.
- Track `$apiCallsUsed` (increment per attempt).

### API failure detection (service limitation)

`GooglePlacesService::searchByNicheAndCity()` does not throw on HTTP failure; it logs and may return an empty array. The command cannot distinguish “zero businesses” from “API error” per call.

**Heuristic (v1):**

1. Missing key → skip entire Step 3 (see above).
2. Key present → run validation; drop niches below `--min-results` normally.
3. If **every** candidate returns `0` results, warn `Places API may have failed — keeping unvalidated niche list`, skip drops, and keep the full Step 2 candidate list.

This matches operator intent for bad key/quota (total failure) without mis-treating a single sparse type as an API outage.

### Log

`Kept {n} niches after validation pass ({dropped} dropped)`

---

## Step 4: Write config

### File shape

`config/niches.php`:

```php
<?php

// Generated by niches:bootstrap on {Y-m-d}
// Edit this file manually to add, remove, or rename niches.
// Re-run niches:bootstrap only if you need to expand from scratch.

return [
    'niches' => [
        ['label' => '…', 'query' => '…', 'primary_type' => '…'],
    ],
    'cities' => [
        'Birmingham',
        // …
    ],
];
```

### Rendering

- Build PHP source via `var_export()` on the return array (or equivalent short-array formatter).
- `file_put_contents($path, $contents, LOCK_EX)`.

### Operator prompts

| Condition | Behaviour |
|-----------|-----------|
| `--dry-run` | Print full PHP to console; no write; no overwrite confirm |
| File exists, interactive | `$this->confirm('config/niches.php already exists. Overwrite?', default: false)` — skip write if declined |
| `--no-interaction` | No confirm; overwrite if not dry-run |

### Pre-write console

Log counts: niches, cities (diff-style summary, not a full file diff).

---

## Consumer updates (same PR)

### `ScanNichesCommand`

```php
$niches = collect(config('niches.niches', []));
$cities = /* --cities option if set, else */ config('niches.cities', []);
```

- Default `--cities`: implode `config('niches.cities')` when non-empty; else retain a small hardcoded fallback string for dev before first bootstrap.
- Continue passing `$niche['label']` and `$niche['query']` to `ScanNicheJob`.

### `config/niches.php` in repo

After first bootstrap run (or migration commit), checked-in config uses nested shape. Until then, command/tests use nested structure in test `config([...])`.

### Niche Opportunity Scanner spec note

The flat-array example in `2026-05-27-niche-opportunity-scanner-design.md` is superseded by this nested shape for new work.

---

## Completion summary

`$this->table()`:

| Metric | Value |
|--------|-------|
| Niches | count after validation |
| Cities | count |
| API calls used | validation attempts (0 if skipped) |
| Config written | path, `dry-run`, or `skipped (declined overwrite)` |

---

## Error handling summary

| Failure | Response |
|---------|----------|
| ONS fetch/parse | Fallback cities + warn |
| Taxonomy fetch/parse | Fallback niches + warn |
| Missing API key | Skip validation + warn |
| All validation calls return 0 (likely API outage) | Warn; keep full Step 2 list |
| Unhandled exception | `$this->error()`, exit 1 |

---

## Files to create / modify

| Path | Action |
|------|--------|
| `app/Console/Commands/NichesBootstrapCommand.php` | Create |
| `config/niches.php` | Restructure to nested shape (bootstrap output or manual migration) |
| `app/Console/Commands/ScanNichesCommand.php` | Read `niches.niches` / `niches.cities` |
| `tests/Feature/NichesBootstrapCommandTest.php` | Create |
| `tests/Feature/ScanNichesCommandTest.php` | Update config shape |

**Not created:** migrations, jobs, UI, scheduler entry.

---

## Testing

All external I/O faked in PHPUnit.

| Case | Assert |
|------|--------|
| Happy path | `Http::fake` ONS + taxonomy; mock Places returns ≥ min-results; file written with nested keys |
| `--dry-run` | No file; output contains `niches` / `cities` |
| ONS failure | Fallback city names present |
| Taxonomy failure | Fallback niche labels present |
| Missing `services.google_places.key` | No Places HTTP; all taxonomy candidates kept |
| Validation drop | Mock low counts; warn output; niche omitted from written config |
| Overwrite declined | `expectsConfirmation` false → file unchanged |
| `niches:scan` | Dispatches jobs using `config('niches.niches')` and default cities from config |

---

## Out of scope

- Paginating ONS API
- GBP scoring or accessibility audits
- `primary_type` in Places search requests
- Scheduled `niches:bootstrap`
- Second config file for cities
