# Changelog

All notable changes to this module are documented here.

## v1.1.0 — 2026-06-08

Added a **canonical health** check (`product_canonical_health`) — the first check that reads **rendered HTML** over HTTP rather than catalog data at rest. On a configurable sample of product pages it flags a missing canonical, more than one canonical tag, or a canonical whose target **redirects (301/302/…) or 404s** (a canonical must resolve to a live 200 URL). New config group `etechflow_seoaudit/canonical/*` (enable, sample size, optional fetch base URL + basic auth) so the check works against origins behind Varnish / basic-auth / an edge gate.

## v1.0.0 — 2026-06-05

Initial public release.

On-demand SEO health audit + 0-100 score. Scans products/categories/CMS for meta/content/link issues, links each finding to the fixing module. CLI scan, admin grid, pluggable checks.
