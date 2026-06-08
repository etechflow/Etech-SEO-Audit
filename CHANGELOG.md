# Changelog

All notable changes to this module are documented here.

## v1.2.0 — 2026-06-08

Added **indexability checks** (the "hidden from Google" catchers) and refactored the HTTP fetching into a shared `Service\HtmlFetcher`:

- **`indexability_noindex`** (critical) — live pages (HTTP 200) returning `noindex` via robots meta or `X-Robots-Tag` header, across sampled product + category pages.
- **`indexability_robots_blocked`** (critical) — live catalogue URLs blocked from crawling by robots.txt (parses the `User-agent: *` Disallow/Allow rules and tests real product/category/home paths).
- **`indexability_sitemap_health`** (warning) — XML sitemap missing, not referenced in robots.txt, or listing URLs that 404 / redirect (follows one level of sitemap-index, samples URLs evenly).

Config reorganised: the shared **Page Fetch** group (`etechflow_seoaudit/fetch/*` — sample size, base URL, basic auth) now drives all rendered-HTML checks, each with its own enable toggle (Canonical, Indexability).

## v1.1.0 — 2026-06-08

Added a **canonical health** check (`product_canonical_health`) — the first check that reads **rendered HTML** over HTTP rather than catalog data at rest. On a configurable sample of product pages it flags a missing canonical, more than one canonical tag, or a canonical whose target **redirects (301/302/…) or 404s** (a canonical must resolve to a live 200 URL). New config group `etechflow_seoaudit/canonical/*` (enable, sample size, optional fetch base URL + basic auth) so the check works against origins behind Varnish / basic-auth / an edge gate.

## v1.0.0 — 2026-06-05

Initial public release.

On-demand SEO health audit + 0-100 score. Scans products/categories/CMS for meta/content/link issues, links each finding to the fixing module. CLI scan, admin grid, pluggable checks.
