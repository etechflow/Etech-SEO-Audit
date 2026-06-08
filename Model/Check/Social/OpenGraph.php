<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Social;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;
use Etechflow\SeoAudit\Model\Config;
use Etechflow\SeoAudit\Service\HtmlFetcher;
use Magento\Framework\App\ResourceConnection;

/**
 * Rendered-HTML check: do product pages carry the Open Graph + Twitter Card tags
 * that control how a link looks when shared (Facebook, WhatsApp, iMessage, X,
 * Slack…)? Missing og:image/og:title means a bare, unclickable-looking link.
 * Also flags an og:url whose domain differs from the store domain — which catches
 * dev/staging-domain leakage after a base-URL/domain swap.
 */
class OpenGraph extends AbstractCheck
{
    private const REQUIRED = ['og:title', 'og:description', 'og:image', 'og:url'];

    public function __construct(
        ResourceConnection $resource,
        Config $config,
        private readonly HtmlFetcher $fetcher
    ) {
        parent::__construct($resource, $config);
    }

    public function getCode(): string { return 'social_open_graph'; }
    public function getLabel(): string { return 'Missing or wrong-domain social share tags (Open Graph / Twitter)'; }
    public function getCategory(): string { return 'social'; }
    public function getSeverity(): string { return 'warning'; }
    public function getFixHint(): string { return 'Review head meta'; }

    /** @return Result[] */
    public function run(): array
    {
        if (!$this->config->socialCheckEnabled() || !$this->fetcher->isAvailable()) {
            return [];
        }
        $storeId = $this->fetcher->defaultStoreId();
        if (!$storeId) {
            return [];
        }
        $storeHost = strtolower($this->fetcher->host());
        $samples   = $this->sampleProductPaths($storeId, $this->config->sampleSize());
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
            $tags    = $this->metaTags($page['body']);
            $missing = [];
            foreach (self::REQUIRED as $req) {
                if (!isset($tags[$req]) || $tags[$req] === '') {
                    $missing[] = $req;
                }
            }
            if (!isset($tags['twitter:card']) || $tags['twitter:card'] === '') {
                $missing[] = 'twitter:card';
            }
            if ($missing) {
                $out[] = new Result('product', $entityId, $path, 'Missing social tags: ' . implode(', ', $missing) . '.', $storeId);
                continue;
            }
            // present — verify og:url is on the store domain (catches domain-swap leakage)
            $ogHost = strtolower((string) parse_url($tags['og:url'], PHP_URL_HOST));
            if ($ogHost !== '' && $storeHost !== '' && $ogHost !== $storeHost) {
                $out[] = new Result('product', $entityId, $path, "og:url points to a different domain ({$ogHost}) than the store ({$storeHost}) — likely a hardcoded/stale domain.", $storeId);
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

    /** @return array<string,string> property/name => content (og:* and twitter:*) */
    private function metaTags(string $html): array
    {
        $pos  = stripos($html, '</head>');
        $head = $pos !== false ? substr($html, 0, $pos) : $html;
        $out  = [];
        if (preg_match_all('/<meta\b[^>]*>/i', $head, $tags)) {
            foreach ($tags[0] as $tag) {
                if (!preg_match('/\b(property|name)\s*=\s*("|\')((?:og|twitter):[a-z:]+)\2/i', $tag, $k)) {
                    continue;
                }
                $key = strtolower($k[3]);
                $val = '';
                if (preg_match('/\bcontent\s*=\s*("|\')(.*?)\1/is', $tag, $c)) {
                    $val = html_entity_decode(trim($c[2]));
                }
                if (!isset($out[$key]) || $out[$key] === '') {
                    $out[$key] = $val;
                }
            }
        }
        return $out;
    }
}
