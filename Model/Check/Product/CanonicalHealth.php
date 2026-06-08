<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Product;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;
use Etechflow\SeoAudit\Model\Config;
use Etechflow\SeoAudit\Service\HtmlFetcher;
use Magento\Framework\App\ResourceConnection;

/**
 * Reads REAL RENDERED HTML (via the shared HtmlFetcher) for a sample of product
 * pages and validates the canonical tag — catching render-time faults the
 * DB-level checks are structurally blind to: no canonical, more than one
 * canonical, or a canonical whose target REDIRECTS (301/302/...) or 404s
 * (a canonical must resolve to a live 200 URL or Google ignores it).
 */
class CanonicalHealth extends AbstractCheck
{
    private const REDIRECT_CODES = [301, 302, 303, 307, 308];

    public function __construct(
        ResourceConnection $resource,
        Config $config,
        private readonly HtmlFetcher $fetcher
    ) {
        parent::__construct($resource, $config);
    }

    public function getCode(): string { return 'product_canonical_health'; }
    public function getLabel(): string { return 'Product canonical problems (missing / duplicate / points to a redirect or 404)'; }
    public function getCategory(): string { return 'links'; }
    public function getSeverity(): string { return 'warning'; }
    public function getFixHint(): string { return 'Canonical & Hreflang'; }

    /** @return Result[] */
    public function run(): array
    {
        if (!$this->config->canonicalCheckEnabled() || !$this->fetcher->isAvailable()) {
            return [];
        }
        $storeId = $this->fetcher->defaultStoreId();
        if (!$storeId) {
            return [];
        }
        $samples = $this->sampleProductPaths($storeId, $this->config->sampleSize());
        if (!$samples) {
            return [];
        }

        $out       = [];
        $reachable = 0;

        foreach ($samples as $row) {
            $entityId = (int) $row['entity_id'];
            $path     = ltrim((string) $row['request_path'], '/');

            $page = $this->fetcher->get('/' . $path, true);
            if ($page['status'] !== 200 || $page['body'] === '') {
                continue;
            }
            $reachable++;

            $canonicals = $this->extractCanonicals($page['body']);
            $n          = count($canonicals);

            if ($n === 0) {
                $out[] = new Result('product', $entityId, $path, 'No canonical tag on the rendered page.', $storeId);
                continue;
            }
            if ($n > 1) {
                $out[] = new Result('product', $entityId, $path, 'Multiple canonical tags (' . $n . '): ' . implode(' , ', array_slice($canonicals, 0, 3)), $storeId);
                continue;
            }

            $href   = $canonicals[0];
            $status = $this->fetcher->get($href, false)['status'];
            if ($status === 0) {
                continue;
            }
            if (in_array($status, self::REDIRECT_CODES, true)) {
                $out[] = new Result('product', $entityId, $path, "Canonical points to a URL that REDIRECTS (HTTP {$status}): {$href} — a canonical must point to a live 200 URL or Google ignores it.", $storeId);
            } elseif ($status >= 400) {
                $out[] = new Result('product', $entityId, $path, "Canonical points to a non-200 URL (HTTP {$status}): {$href}.", $storeId);
            }
        }

        if ($reachable === 0) {
            return [new Result('config', null, $this->fetcher->resolve('/'), "Canonical check could not fetch any rendered page (origin behind Varnish/basic-auth/edge gate). Set Stores > Config > Etechflow > SEO Audit > Page Fetch (base URL + optional basic auth), then re-run.", $storeId)];
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function sampleProductPaths(int $storeId, int $limit): array
    {
        $conn   = $this->connection();
        $select = $conn->select()
            ->from($this->table('url_rewrite'), ['entity_id', 'request_path'])
            ->where('entity_type = ?', 'product')
            ->where('store_id = ?', $storeId)
            ->where('redirect_type = ?', 0)
            ->where('request_path NOT LIKE ?', '%/%')
            ->order('entity_id DESC')
            ->limit($limit);

        return $conn->fetchAll($select);
    }

    /** @return string[] */
    private function extractCanonicals(string $html): array
    {
        $pos  = stripos($html, '</head>');
        $head = $pos !== false ? substr($html, 0, $pos) : $html;

        if (!preg_match_all('/<link\b[^>]*\brel\s*=\s*("|\')canonical\1[^>]*>/i', $head, $tags)) {
            return [];
        }
        $hrefs = [];
        foreach ($tags[0] as $tag) {
            if (preg_match('/\bhref\s*=\s*("|\')(.*?)\1/i', $tag, $m)) {
                $hrefs[] = html_entity_decode(trim($m[2]));
            }
        }
        return $hrefs;
    }
}
