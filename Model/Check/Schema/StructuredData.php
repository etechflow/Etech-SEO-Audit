<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Schema;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;
use Etechflow\SeoAudit\Model\Config;
use Etechflow\SeoAudit\Service\HtmlFetcher;
use Magento\Framework\App\ResourceConnection;

/**
 * Rendered-HTML check: do product pages expose Product structured data as
 * JSON-LD (Google's preferred format for rich results — price, availability,
 * ratings)? Flags pages with no JSON-LD at all, or JSON-LD that lacks a Product
 * type. Handles @graph containers and arrays.
 */
class StructuredData extends AbstractCheck
{
    public function __construct(
        ResourceConnection $resource,
        Config $config,
        private readonly HtmlFetcher $fetcher
    ) {
        parent::__construct($resource, $config);
    }

    public function getCode(): string { return 'schema_product_jsonld'; }
    public function getLabel(): string { return 'Missing Product structured data (JSON-LD)'; }
    public function getCategory(): string { return 'schema'; }
    public function getSeverity(): string { return 'warning'; }
    public function getFixHint(): string { return 'Rich Snippets'; }

    /** @return Result[] */
    public function run(): array
    {
        if (!$this->config->schemaCheckEnabled() || !$this->fetcher->isAvailable()) {
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

        $out = [];
        foreach ($samples as $row) {
            $entityId = (int) $row['entity_id'];
            $path     = ltrim((string) $row['request_path'], '/');

            $page = $this->fetcher->get('/' . $path, true);
            if ($page['status'] !== 200 || $page['body'] === '') {
                continue;
            }
            $blocks = $this->jsonLdBlocks($page['body']);
            if (!$blocks) {
                $out[] = new Result('product', $entityId, $path, 'No JSON-LD structured data on the page. Add Product schema for rich results (price, availability, ratings).', $storeId);
                continue;
            }
            $hasProduct = false;
            foreach ($blocks as $data) {
                if ($this->hasType($data, 'Product')) {
                    $hasProduct = true;
                    break;
                }
            }
            if (!$hasProduct) {
                $out[] = new Result('product', $entityId, $path, 'JSON-LD present but no Product schema (found: ' . implode(', ', array_slice($this->typesFound($blocks), 0, 5)) . ').', $storeId);
            }
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function sampleProductPaths(int $storeId, int $limit): array
    {
        $conn = $this->connection();
        return $conn->fetchAll(
            $conn->select()->from($this->table('url_rewrite'), ['entity_id', 'request_path'])
                ->where('entity_type = ?', 'product')->where('store_id = ?', $storeId)
                ->where('redirect_type = ?', 0)->where('request_path NOT LIKE ?', '%/%')
                ->order('entity_id DESC')->limit($limit)
        );
    }

    /** @return array<int,mixed> decoded JSON-LD blocks */
    private function jsonLdBlocks(string $html): array
    {
        if (!preg_match_all('/<script\b[^>]*type\s*=\s*("|\')application\/ld\+json\1[^>]*>(.*?)<\/script>/is', $html, $m)) {
            return [];
        }
        $out = [];
        foreach ($m[2] as $json) {
            $decoded = json_decode(trim($json), true);
            if ($decoded !== null) {
                $out[] = $decoded;
            }
        }
        return $out;
    }

    private function hasType($data, string $type): bool
    {
        foreach ($this->typesFound([$data]) as $t) {
            if (strcasecmp($t, $type) === 0) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] every @type string anywhere in the decoded blocks */
    private function typesFound(array $blocks): array
    {
        $types = [];
        $walk  = function ($node) use (&$walk, &$types) {
            if (is_array($node)) {
                foreach ($node as $key => $val) {
                    if ($key === '@type') {
                        foreach ((array) $val as $t) {
                            if (is_string($t)) {
                                $types[] = $t;
                            }
                        }
                    } elseif (is_array($val)) {
                        $walk($val);
                    }
                }
            }
        };
        foreach ($blocks as $b) {
            $walk($b);
        }
        return array_values(array_unique($types));
    }
}
