# Wave PR merge guide (#18–#24)

Stack of follow-up refactor PRs after **#17** (merged to `main` at `79e2fc8`). Each PR is individually **MERGEABLE / CLEAN** against `main`, but sequential merges produce conflicts.

## Recommended merge order

1. **#18** — `refactor/backend-audit-wave-3` (largest: backend P2/P3 + early F1-04 touches)
2. **#19** — wave 4
3. **#20** — wave 5
4. **#21** — wave 6
5. **#22** — wave 7
6. **#23** — wave 8
7. **#24** — wave 9

Merge in numeric order. Do not reorder #18 after later waves — it establishes backend types and resources later PRs assume.

## Sequential merge simulation (2026-06-05)

```bash
git checkout -b merge-sim origin/main
git merge origin/refactor/backend-audit-wave-3    # clean (fast-forward)
git merge origin/refactor/backend-audit-wave-4    # CONFLICT: resources/css/components.css
```

First conflict appears at **#19** merging into a branch that already has **#18**. Expect further `components.css` conflicts on every subsequent merge.

## High-overlap files

| File | PRs |
|------|-----|
| `resources/css/components.css` | #18–#24 (all) |
| `resources/js/Pages/Settings/Index.jsx` | #18, #21, #24 |
| `resources/js/Pages/Ignored/Index.jsx` | #18, #20, #24 |
| `resources/js/Pages/Outreach/Index.jsx` | #19, #23 |
| `resources/js/Components/AgencyBookingSettingsCard.jsx` | #19, #24 |
| `resources/js/Pages/Prospect/Show.jsx` | #18, #22 |
| `resources/js/Pages/Reports/Index.jsx` | #18, #23 |
| `resources/js/Components/ui/Pagination.jsx` | #20, #24 |

### Backend overlaps

| Area | PRs | Resolution |
|------|-----|------------|
| Ignored prospects (controller, request, service) | #18, #20 | Prefer **#20**: `FilterIgnoredProspectsRequest` + `paginateForUser()` |
| `CaptureScreenshotJob` | #18, #20 | Take **#20** lock/docs changes |
| `OutreachController` | #18, #19 | Union both; #19 removes dead code |

## Conflict resolution playbook

### `components.css`

- **Do not pick one side wholesale.** Union utility blocks from both branches.
- Later waves add classes; earlier waves add different utilities. Missing a block regresses a merged page.
- After each merge conflict, search for duplicate selectors and merge rules (same class defined twice).

### JSX pages (Settings, Ignored, Prospect/Show, etc.)

- Prefer the **later wave** for F1-04 (zero inline `style={{}}`).
- Keep **earlier wave** backend-driven props/structure if the later wave only changed styling.
- When both change logic, read the later PR description and tests.

### `Pagination.jsx` (#20 vs #24)

- **#24** is the final styling pass; prefer its markup/classes after #20 is merged.

## Post-merge checklist

After each merge (or after the full stack):

```bash
composer install   # if lock changed
npm ci
php artisan test
npm run build
./vendor/bin/pint --dirty
```

Spot-check pages touched by the merged wave:

- Settings, Ignored, Prospect show, Outreach, Reports, Search/Saved history

## Wave 10 (#25) and this stack

**Wave 10** (`Report/Public`, `Book/Index`) branches from `main` and adds `.public-report-*` classes to `components.css`. Options:

1. **Merge #18–#24 first**, then merge #25 — expect one `components.css` conflict (union public-report block with accumulated utilities).
2. **Merge #25 before the stack** — same `components.css` conflict when stacking #18+.

Either order works; union `components.css` the same way.

## One-shot merge script (local only)

```bash
set -e
BASE=origin/main
git checkout -B merge-sim "$BASE"
for b in wave-3 wave-4 wave-5 wave-6 wave-7 wave-8 wave-9; do
  git merge --no-edit "origin/refactor/backend-audit-$b" || break
done
git diff --name-only --diff-filter=U
```

Resolve conflicts, `git add` + `git commit`, repeat until all seven merge. Run the post-merge checklist before pushing.
