<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Product;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;
use Etechflow\SeoAudit\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The only check in the pool that reads REAL RENDERED HTML over HTTP rather than
 * catalog data at rest — so it catches render-time canonical faults the DB-level
 * checks are structurally blind to. For a sample of product pages it flags:
 *   - no canonical tag
 *   - more than one canonical tag
 *   - a canonical that points to a URL which REDIRECTS (301/302/...) or 404s
 *     (a canonical must resolve to a live 200 URL or Google ignores it).
 *
 * Origins behind Varnish / basic-auth / an edge gate can't be reached at their
 * public URL from the server, so the fetch endpoint (+ optional basic auth) is
 * configurable; left blank it uses the store's secure base URL.
 */
class CanonicalHealth extends AbstractCheck
{
    private const REDIRECT_CODES = [301, 302, 303, 307, 308];

    public function __construct(
        ResourceConnection $resource,
        Config $config,
        private readonly StoreManagerInterface $storeManager
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
        if (!$this->config->canonicalCheckEnabled() || !function_exists('curl_init')) {
            return [];
        }

        $store = $this->storeManager->getDefaultStoreView();
        if (!$store) {
            return [];
        }
        $storeId    = (int) $store->getId();
        $publicBase = rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true), '/');
        $host       = (string) parse_url($publicBase, PHP_URL_HOST);
        $fetchBase  = rtrim($this->config->canonicalFetchBaseUrl() ?: $publicBase, '/');
        $auth       = $this->config->canonicalBasicAuth();

        $samples = $this->sampleProductPaths($storeId, $this->config->canonicalSampleSize());
        if (!$samples) {
            return [];
        }

        $out       = [];
        $reachable = 0;

        foreach ($samples as $row) {
            $entityId = (int) $row['entity_id'];
            $path     = ltrim((string) $row['request_path'], '/');

            [$status, $body] = $this->fetch($fetchBase . '/' . $path, $host, $auth, true);
            if ($status !== 200 || $body === '') {
                continue;
            }
            $reachable++;

            $canonicals = $this->extractCanonicals($body);
            $n          = count($canonicals);

            if ($n === 0) {
                $out[] = new Result('product', $entityId, $path, 'No canonical tag on the rendered page.', $storeId);
                continue;
            }
            if ($n > 1) {
                $out[] = new Result('product', $entityId, $path, 'Multiple canonical tags (' . $n . '): ' . implode(' , ', array_slice($canonicals, 0, 3)), $storeId);
                continue;
            }

            $href         = $canonicals[0];
            $targetStatus = $this->statusOfCanonicalTarget($href, $host, $fetchBase, $auth);
            if ($targetStatus === null) {
                continue;
            }
            if (in_array($targetStatus, self::REDIRECT_CODES, true)) {
                $out[] = new Result('product', $entityId, $path, "Canonical points to a URL that REDIRECTS (HTTP {$targetStatus}): {$href} — a canonical must point to a live 200 URL or Google ignores it.", $storeId);
            } elseif ($targetStatus >= 400) {
                $out[] = new Result('product', $entityId, $path, "Canonical points to a non-200 URL (HTTP {$targetStatus}): {$href}.", $storeId);
            }
        }

        if ($reachable === 0) {
            return [new Result(
                'config',
                null,
                $fetchBase,
                "Canonical check could not fetch any rendered page from {$fetchBase} (origin behind Varnish/basic-auth/edge gate). Set 'Canonical check: fetch base URL' (an internal origin) and optional basic auth under Stores > Config > Etechflow > SEO Audit, then re-run.",
                $storeId
            )];
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

    /**
     * Status of the canonical target WITHOUT following redirects. Same-host
     * targets are routed through the fetch base so gated origins still resolve.
     */
    private function statusOfCanonicalTarget(string $href, string $host, string $fetchBase, ?string $auth): ?int
    {
        $hrefHost = (string) parse_url($href, PHP_URL_HOST);
        if ($hrefHost !== '' && $host !== '' && strcasecmp($hrefHost, $host) === 0) {
            $path  = (string) parse_url($href, PHP_URL_PATH);
            $query = (string) parse_url($href, PHP_URL_QUERY);
            $url   = $fetchBase . $path . ($query !== '' ? '?' . $query : '');
            [$status] = $this->fetch($url, $host, $auth, false);
            return $status ?: null;
        }
        [$status] = $this->fetch($href, $hrefHost ?: $host, $auth, false);
        return $status ?: null;
    }

    /** @return array{0:int,1:string} [httpStatus, body] */
    private function fetch(string $url, string $host, ?string $auth, bool $followRedirects): array
    {
        $headers = ['X-Forwarded-Proto: https', 'Accept: text/html'];
        if ($host !== '') {
            $headers[] = 'Host: ' . $host;
        }
        $bust = (strpos($url, '?') !== false ? '&' : '?') . '_seoaudit=' . substr(md5($url), 0, 8);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url . $bust,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Etechflow-SeoAudit/1.0',
        ]);
        if ($auth !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth);
        }
        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return [$status, $followRedirects ? $body : ''];
    }
}
