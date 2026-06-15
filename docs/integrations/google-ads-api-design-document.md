# Google Ads API — Design documentation

> **Application outcome (June 2026): rejected.** Google Ads API Compliance: *“Tools that offer only keyword research are not allowed.”* Use [Keyword Planner](../cpc-benchmarks.md) for CPC instead. This document is kept for reference only.

Design document for the **nthdesigns Prospect Scanner** Google Ads API integration. Originally submitted for **Basic Access** (export to PDF for API Center).

**Related:** [Google Ads CPC integration](google-ads-cpc.md) · [CPC benchmarks](../cpc-benchmarks.md)

**Google template:** [Official design doc template](https://docs.google.com/document/d/1i1xc8kChjZISoSclTAaRReDwWZQDCcYDGOUJPR8hGJM/edit)

---

## Tool name

nthdesigns Prospect Scanner — CPC Benchmark Module

## Company

**Company:** nthdesigns  
**Website:** https://nthdesigns.co.uk  
**Contact:** *(add your email before export)*

---

## 1. Purpose

The nthdesigns Prospect Scanner is an **internal business development tool** used by nthdesigns staff to identify UK local businesses with weak digital presence (Google Business Profile and website accessibility), generate audit reports, and draft personalised outreach emails.

The Google Ads API integration is a **small, optional module** that fetches **local keyword cost-per-click (CPC) benchmarks** to support GBP-focused outreach copy. Example: *“Businesses in your category in Birmingham spend approximately £X per click on Google Ads…”*

The tool does **not** create, manage, or modify Google Ads campaigns, ad groups, ads, or keywords in Google Ads.

---

## 2. Users and access

- **Audience:** nthdesigns employees only (authenticated internal web app).
- **Access model:** Login required (Laravel Breeze). No public API. No third-party or client access.
- **Deployment:** Private server (Laravel Cloud). Not listed on Google Workspace Marketplace or any public directory.

---

## 3. Google Ads API usage

**Permissible use requested:** Researching keywords and recommendations

**API version:** v18 (REST)

### Services and methods used (read-only)

| Service | Method / endpoint | Purpose |
|---|---|---|
| KeywordPlanIdeaService | `customers/{customerId}:generateKeywordIdeas` | Fetch average CPC metrics for locally seeded commercial keywords |
| GeoTargetConstantService | `geoTargetConstants:suggestGeoTargetConstants` | Resolve city + country to a geo target constant for local CPC lookup |

**Services NOT used:** CampaignService, AdGroupService, AdService, KeywordService (mutations), BillingService, or any mutate/write operations.

### Typical request flow

1. Operator enters niche (e.g. “dental practice”) and city (e.g. “Birmingham”) in the internal UI.
2. App builds 3–5 seed keywords (e.g. “dental practice Birmingham”, “dental practice in Birmingham”).
3. App calls `suggestGeoTargetConstants` to resolve “Birmingham, United Kingdom” → geo target ID (cached in config when possible).
4. App calls `generateKeywordIdeas` with seed keywords, English language constant, and geo target.
5. App reads `keywordIdeaMetrics.averageCpcMicros` (fallback: top-of-page bid micros), computes **median CPC**, stores result in our database.
6. CPC is used **only** when generating internal outreach email drafts. It is **not** written back to Google Ads.

**Trigger:** Operator-initiated only (button: “Fetch from Google Ads”). Auto-fetch on search create is **disabled by default** (`GOOGLE_ADS_CPC_AUTO_FETCH=false`).

---

## 4. Data handling

**Stored locally (PostgreSQL):**

- Median CPC value (£)
- Source label (`google_ads`)
- Seed keywords used for the lookup
- Geo target resource name

**Not stored:** Full Google Ads API responses, competitor data, or personally identifiable information from Google Ads.

CPC data is used solely to enrich internal outreach email drafts. Operators may override or enter CPC manually without calling the API.

---

## 5. Authentication and security

- **OAuth 2.0** with refresh token (scope: `https://www.googleapis.com/auth/adwords`).
- Credentials stored in **server environment variables** only:
  - `GOOGLE_ADS_CLIENT_ID`
  - `GOOGLE_ADS_CLIENT_SECRET`
  - `GOOGLE_ADS_REFRESH_TOKEN`
  - `GOOGLE_ADS_DEVELOPER_TOKEN`
  - `GOOGLE_ADS_CUSTOMER_ID`
- Access tokens cached server-side (~55 minutes). Refresh tokens are never exposed to the browser or end users.
- All API calls originate from the application server, not from client browsers.

---

## 6. Error handling and logging

- API failures are logged server-side (`google_ads.cpc_lookup_failed`, `google_ads.geo_target_failed`) with niche/city context — no credentials in logs.
- Failures do **not** block other app functionality; operators can enter CPC manually.
- No automatic retries beyond standard HTTP client behaviour.

---

## 7. Expected API volume

- **Estimated usage:** 5–50 keyword lookups per week during active outreach periods.
- **Operations per lookup:** 1–2 API calls (`suggestGeoTargetConstants` when geo not cached, plus `generateKeywordIdeas`).
- **Daily estimate:** Well under 100 operations/day — far below Basic Access limits (15,000/day).

---

## 8. What the tool does NOT do

- Does not create, edit, pause, or delete campaigns, ad groups, ads, or keywords in Google Ads.
- Does not access billing, conversion, or performance reporting beyond keyword idea metrics.
- Does not provide Google Ads management features to external users or clients.
- Does not resell or redistribute Google Ads data.

---

## 9. Architecture overview

```text
Operator (browser)
    → Laravel web app (authenticated)
    → MarketCpcLookupService / GoogleAdsKeywordPlanService
    → Google Ads REST API (read-only keyword planning)
    → PostgreSQL (market_cpc_defaults, searches)
    → Outreach email generator (uses stored CPC in AI prompt)
```

**Stack:** Laravel 13, PostgreSQL, internal Inertia/React UI. REST client (no third-party Google Ads PHP library).

---

## 10. Compliance

- **Internal use only** — tool used exclusively by nthdesigns for its own prospecting.
- **RMF:** Tool is exempt (under 15,000 requests/day, no external campaign management product).
- Operators send outreach manually outside the app; the tool generates drafts only.

---

## Application form — suggested answers

| Field | Answer |
|---|---|
| How will you use the API? | Internal keyword research for outreach copy |
| Permissible use | Researching keywords and recommendations |
| Third-party access? | No — internal use only |
| Create or manage campaigns? | No |
| Demo access | Internal tool — not publicly accessible. Demo available on request via scheduled screen share. |

---

## Export for upload

Google accepts `.pdf`, `.doc`, or `.rtf` only.

A Word version is available at [google-ads-api-design-document.docx](google-ads-api-design-document.docx). Add your contact email in the Company section before upload.

Alternatively:

1. Add your contact email in the Company section above.
2. Copy this document into [Google Docs](https://docs.google.com) or Word.
3. **File → Download → PDF Document (.pdf)** if PDF is preferred.
4. Upload to the Basic Access application in [API Center](https://ads.google.com/aw/apicenter).
