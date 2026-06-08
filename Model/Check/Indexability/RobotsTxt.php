<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Indexability;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;
use Etechflow\SeoAudit\Model\Config;
use Etechflow\SeoAudit\Service\HtmlFetcher;
use Magento\Framework\App\ResourceConnection;

/**
 * Flags real catalogue URLs that robots.txt blocks from crawling. Parses the
 * User-agent:* Disallow/Allow rules and tests a sample product + category +
 * the homepage against them — a Disallow that covers live pages (or a blanket
 * "Disallow: /") quietly removes them from Google.
 */
class RobotsTxt extends AbstractCheck
{
    public function __construct(
        ResourceConnection $resource,
        Config $config,
        private readonly HtmlFetcher $fetcher
    ) {
        parent::__construct($resource, $config);
    }

    public function getCode(): string { return 'indexability_robots_blocked'; }
    public function getLabel(): string { return 'Live URLs blocked by robots.txt'; }
    public function getCategory(): string { return 'links'; }
    public function getSeverity(): string { return 'critical'; }
    public function getFixHint(): string { return 'Review robots.txt'; }

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

        $robots = $this->fetcher->get('/robots.txt', true);
        if ($robots['status'] !== 200 || trim($robots['body']) === '') {
            return [];
        }
        [$disallow, $allow] = $this->parseRules($robots['body']);
        if (!$disallow) {
            return [];
        }

        $out = [];
        foreach ($this->candidatePaths($storeId) as $label => $path) {
            if ($path === '' ) {
                continue;
            }
            if ($this->isBlocked($path, $disallow, $allow)) {
                $out[] = new Result(
                    'url',
                    null,
                    $path,
                    "robots.txt blocks this live {$label} URL from crawling (matches a Disallow rule). Remove or narrow the rule so Google can crawl it.",
                    $storeId
                );
            }
        }
        return $out;
    }

    /** @return array{0:string[],1:string[]} [disallow, allow] for User-agent:* */
    private function parseRules(string $body): array
    {
        $disallow = [];
        $allow    = [];
        $inStar   = false;
        foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '') {
                continue;
            }
            if (preg_match('/^user-agent\s*:\s*(.+)$/i', $line, $m)) {
                $inStar = trim($m[1]) === '*';
                continue;
            }
            if (!$inStar) {
                continue;
            }
            if (preg_match('/^disallow\s*:\s*(.*)$/i', $line, $m)) {
                $p = trim($m[1]);
                if ($p !== '') {
                    $disallow[] = $p;
                }
            } elseif (preg_match('/^allow\s*:\s*(.*)$/i', $line, $m)) {
                $p = trim($m[1]);
                if ($p !== '') {
                    $allow[] = $p;
                }
            }
        }
        return [$disallow, $allow];
    }

    /** @return array<string,string> */
    private function candidatePaths(int $storeId): array
    {
        $conn = $this->connection();
        $product = (string) $conn->fetchOne(
            $conn->select()->from($this->table('url_rewrite'), ['request_path'])
                ->where('entity_type = ?', 'product')->where('store_id = ?', $storeId)
                ->where('redirect_type = ?', 0)->where('request_path NOT LIKE ?', '%/%')
                ->order('entity_id DESC')->limit(1)
        );
        $category = (string) $conn->fetchOne(
            $conn->select()->from($this->table('url_rewrite'), ['request_path'])
                ->where('entity_type = ?', 'category')->where('store_id = ?', $storeId)
                ->where('redirect_type = ?', 0)->order('entity_id DESC')->limit(1)
        );
        return [
            'homepage' => '/',
            'product'  => $product !== '' ? '/' . ltrim($product, '/') : '',
            'category' => $category !== '' ? '/' . ltrim($category, '/') : '',
        ];
    }

    private function isBlocked(string $path, array $disallow, array $allow): bool
    {
        $blockLen = $this->longestPrefix($path, $disallow);
        if ($blockLen < 0) {
            return false;
        }
        $allowLen = $this->longestPrefix($path, $allow);
        return $allowLen < $blockLen; // a more-specific Allow wins (simplified)
    }

    /** Longest matching rule prefix length, or -1 if none. Supports a trailing *. */
    private function longestPrefix(string $path, array $rules): int
    {
        $best = -1;
        foreach ($rules as $rule) {
            $pattern = rtrim($rule, '*');
            if ($pattern === '/' || str_starts_with($path, $pattern)) {
                $best = max($best, strlen($pattern));
            }
        }
        return $best;
    }
}
