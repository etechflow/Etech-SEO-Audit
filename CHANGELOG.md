# Changelog

All notable changes to this module are documented here.

## v1.4.1 — 2026-06-08

Fixed a false-positive: the image-alt check now reads **rendered HTML** instead of the `image_label` DB attribute. The old check (`product_missing_image_alt`) flagged every product with an empty `image_label`, but most themes (Hyvä, Luma) fall back to the product **name** for the `<img alt>`, so an empty label rarely means a truly missing alt — it over-reported badly (e.g. ~2,900 false notices on a Hyvä store). Replaced with `content_image_alt`: it samples product pages and flags a page only when a product image is present but **neither** a gallery caption/label **nor** an `<img alt>` supplies alt text (gated by the same on-page/page-fetch settings as the H1 check).

## v1.4.0 — 2026-06-08

Added **duplication & on-page** checks:

- **`product_duplicate_meta_description`** (warning) — products sharing an identical meta description.
- **`product_meta_description_length`** (notice) — meta descriptions outside the configured length range.
- **`product_missing_image_alt`** (notice) — visible products with a base image but no alt text (image label).
- **`onpage_h1`** (warning, rendered) — product pages with a missing or duplicate H1.

## v1.3.0 — 2026-06-08

Added **visibility** rendered-HTML checks:

- **`social_open_graph`** (warning) — product pages missing Open Graph / Twitter Card tags, or an `og:url` whose domain differs from the store domain (catches dev/staging-domain leakage after a base-URL/domain swap).
- **`schema_product_jsonld`** (warning) — product pages with no Product JSON-LD structured data. Walks `@graph` containers and arrays to find a Product `@type`.

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
