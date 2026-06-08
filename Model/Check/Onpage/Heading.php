<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Onpage;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;
use Etechflow\SeoAudit\Model\Config;
use Etechflow\SeoAudit\Service\HtmlFetcher;
use Magento\Framework\App\ResourceConnection;

/**
 * Rendered-HTML check: each product page should have exactly one <h1>. Zero H1s
 * weakens the page's primary topic signal; multiple H1s dilute it. Samples
 * product pages over HTTP.
 */
class Heading extends AbstractCheck
{
    public function __construct(
        ResourceConnection $resource,
        Config $config,
        private readonly HtmlFetcher $fetcher
    ) {
        parent::__construct($resource, $config);
    }

    public function getCode(): string { return 'onpage_h1'; }
    public function getLabel(): string { return 'Product pages with a missing or duplicate H1'; }
    public function getCategory(): string { return 'content'; }
    public function getSeverity(): string { return 'warning'; }
    public function getFixHint(): string { return 'Review template'; }

    /** @return Result[] */
    public function run(): array
    {
        if (!$this->config->onpageCheckEnabled() || !$this->fetcher->isAvailable()) {
            return [];
        }
        $storeId = $this->fetcher->defaultStoreId();
        if (!$storeId) {
            return [];
        }
        $conn    = $this->connection();
        $samples = $conn->fetchAll(
            $conn->select()->from($this->table('url_rewrite'), ['entity_id', 'request_path'])
                ->where('entity_type = ?', 'product')->where('store_id = ?', $storeId)
                ->where('redirect_type = ?', 0)->where('request_path NOT LIKE ?', '%/%')
                ->order('entity_id DESC')->limit($this->config->sampleSize())
        );
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
            $count = preg_match_all('/<h1[\s>]/i', $page['body']);
            if ($count === 0) {
                $out[] = new Result('product', $entityId, $path, 'Page has no H1 heading.', $storeId);
            } elseif ($count > 1) {
                $out[] = new Result('product', $entityId, $path, "Page has {$count} H1 headings — there should be exactly one.", $storeId);
            }
        }
        return $out;
    }
}
