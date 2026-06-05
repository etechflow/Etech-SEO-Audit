# Etechflow_SeoAudit

On-demand **SEO health audit + score** for Magento 2. Scans your products, categories and CMS pages for SEO problems, gives the store a **0-100 health score**, and lists every issue in an admin grid — each one tagged with the **suite module that fixes it**. Part of the **Etechflow SEO Suite**.

## What it checks

| Area | Checks |
|------|--------|
| **Meta** | products/categories/CMS missing meta title or description, **duplicate** meta titles, meta titles too short/long |
| **Content** | thin or missing product descriptions, products with no base image |
| **Links** | logged **404s** and **redirect chains** (via Etechflow_RedirectManager — soft dependency) |

Each check is a small class implementing `Api\CheckInterface`, registered into the scanner pool in `etc/di.xml` — so you can add your own checks without touching core.

## How it works

- **Scanner** runs every registered check (fast, SQL-backed), replaces the issue table with fresh findings, and stores a summary (score + counts) via `FlagManager`.
- **ScoreCalculator** turns issue counts into a transparent 0-100 score: `penalty = critical×3 + warning×1 + notice×0.3`, normalised against the number of products + categories + CMS pages.
- **Admin dashboard** (Content → SEO Audit) shows the score, a severity/area breakdown, a **Run SEO Scan Now** button, and the full issue grid. Each row links to the entity and names the **Fix with** module.
- **CLI**: `bin/magento etechflow:seoaudit:scan`.

## The suite hook

The audit is the *finder*; the rest of the Etechflow SEO Suite is the *fixer*. Findings point at the tool that resolves them:

- Missing / duplicate / poor meta → **Etechflow_MetaTemplates** or **Etechflow_AiSeo**
- 404s & redirect chains → **Etechflow_RedirectManager**
- (Structured-data gaps → **Etechflow_RichSnippets**)

## Install

```bash
composer require etechflow/module-seo-audit
bin/magento module:enable Etechflow_SeoAudit
bin/magento setup:upgrade
bin/magento setup:di:compile     # production
```

## Configure

**Stores → Configuration → Etechflow → SEO Audit** — tune the meta-title/description length thresholds and the thin-description cutoff. Then **Content → SEO Audit → Run SEO Scan Now**.

## License

Proprietary — © eTechFlow.
