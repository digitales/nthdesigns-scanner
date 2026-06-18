# Warmup shared seed pool — Implementation Plan

**Goal:** Cross-user seed network for Agency+ tiers with bounce exclusion and white-label admin health view.

**Spec:** `docs/superpowers/specs/2026-06-18-warmup-shared-pool-design.md`

---

## Status: Implemented

All tasks from the spec are complete (June 2026).

### Key files

| Area | Files |
|------|-------|
| Pool selection | `app/Services/WarmupSeedPoolService.php`, `config/warmup_pool.php` |
| Jobs | `app/Jobs/WarmupPoolHealthJob.php` |
| Admin | `app/Http/Controllers/WarmupPoolController.php`, `resources/js/Pages/Warmup/Admin/Pool.jsx` |
| UI | `Warmup/Index.jsx`, `Warmup/Connect.jsx`, `WarmupMailboxCard.jsx` |
| Bounce handling | `SendWarmupEmailJob` recipient-rejected → `status = bounced` |
| Tests | `tests/Unit/WarmupSeedPoolServiceTest.php`, `tests/Feature/WarmupSharedPoolTest.php` |

### Verification

```bash
php artisan test --filter='WarmupSeedPool|WarmupSharedPool'
```
