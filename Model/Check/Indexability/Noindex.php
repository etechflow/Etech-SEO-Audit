<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Indexability;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;
use Etechflow\SeoAudit\Model\Config;
use Etechflow\SeoAudit\Service\HtmlFetcher;
use Magento\Framework\App\ResourceConnection;

/**
 * "Hidden from Google" — a live page (HTTP 200) that tells search engines NOT to
 * index it, via a robots meta tag or an X-Robots-Tag response header containing
 * "noindex". One wrong toggle and a product/category silently drops out of the
 * index with no visible error. Samples product + category pages over HTTP.
 */
class Noindex extends AbstractCheck
{
    public function __construct(
        ResourceConnection $resource,
        Config $config,
        private readonly HtmlFetcher $fetcher
    ) {
        parent::__construct($resource, $config);
    }

    public function getCode(): string { return 'indexability_noindex'; }
    public function getLabel(): string { return 'Live pages hidden from Google (noindex)'; }
    public function getCategory(): string { return 'links'; }
    public function getSeverity(): string { return 'critical'; }
    public function getFixHint(): string { return 'Review robots meta'; }

    /** @return Result[] */
    public function run(): array
    {
        if (!$this->config->indexabilityCheckEnabled() || !$this->fetcher->isAvailable()) {
            return [];
        }
        $storeId = $this->fetcher->defaultStoreId();
        if (!$storeId) {
            return [];
        }
        $limit   = $this->config->sampleSize();
        $samples = array_merge(
            $this->samplePaths('product', $storeId, $limit, true),
            $this->samplePaths('category', $storeId, max(5, (int) ($limit / 2)), false)
        );
        if (!$samples) {
            return [];
        }

        $out = [];
        foreach ($samples as $row) {
            $type     = (string) $row['_etype'];
            $entityId = (int) $row['entity_id'];
            $path     = ltrim((string) $row['request_path'], '/');

            $page = $this->fetcher->get('/' . $path, true);
            if ($page['status'] !== 200) {
                continue;
            }
            if ($this->isNoindex($page['headers'], $page['body'])) {
                $out[] = new Result(
                    $type,
                    $entityId,
                    $path,
                    'Live page (HTTP 200) returns NOINDEX — it is hidden from Google. Remove the noindex robots directive if this page should rank.',
                    $storeId
                );
            }
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function samplePaths(string $entityType, int $storeId, int $limit, bool $bareOnly): array
    {
        $conn   = $this->connection();
        $select = $conn->select()
            ->from($this->table('url_rewrite'), ['entity_id', 'request_path'])
            ->where('entity_type = ?', $entityType)
            ->where('store_id = ?', $storeId)
            ->where('redirect_type = ?', 0)
            ->order('entity_id DESC')
            ->limit($limit);
        if ($bareOnly) {
            $select->where('request_path NOT LIKE ?', '%/%');
        }
        $rows = $conn->fetchAll($select);
        foreach ($rows as &$r) {
            $r['_etype'] = $entityType;
        }
        return $rows;
    }

    private function isNoindex(array $headers, string $html): bool
    {
        $xRobots = strtolower((string) ($headers['x-robots-tag'] ?? ''));
        if (str_contains($xRobots, 'noindex') || str_contains($xRobots, 'none')) {
            return true;
        }
        $pos  = stripos($html, '</head>');
        $head = $pos !== false ? substr($html, 0, $pos) : $html;
        if (preg_match_all('/<meta\b[^>]*\bname\s*=\s*("|\')(robots|googlebot)\1[^>]*>/i', $head, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/\bcontent\s*=\s*("|\')(.*?)\1/i', $tag, $m)) {
                    $content = strtolower($m[2]);
                    if (str_contains($content, 'noindex') || str_contains($content, 'none')) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
