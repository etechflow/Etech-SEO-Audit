<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Content;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;
use Etechflow\SeoAudit\Model\Config;
use Etechflow\SeoAudit\Service\HtmlFetcher;
use Magento\Framework\App\ResourceConnection;

/**
 * Rendered-HTML check: does a product page's main image actually render WITHOUT
 * alt text? This replaces a naive DB check on the image_label attribute, which
 * over-reports — most themes (Hyva, Luma) fall back to the product NAME for the
 * <img alt>, so an empty image_label rarely means a truly empty rendered alt.
 *
 * On a sample of product pages we treat the image as having alt text if EITHER:
 *   - the Hyva gallery JSON config carries a non-empty "caption"/"label", OR
 *   - a product-image <img> (catalog/product, /media/catalog, or Cloudinary src)
 *     has a non-empty alt attribute.
 * A page is flagged only when a product image is present but none of the above
 * supplies alt text. Pages with no detectable product image are skipped (that is
 * a missing-image concern, not a missing-alt one).
 */
class ImageAlt extends AbstractCheck
{
    public function __construct(
        ResourceConnection $resource,
        Config $config,
        private readonly HtmlFetcher $fetcher
    ) {
        parent::__construct($resource, $config);
    }

    public function getCode(): string { return 'content_image_alt'; }
    public function getLabel(): string { return 'Product pages whose main image renders with no alt text'; }
    public function getCategory(): string { return 'content'; }
    public function getSeverity(): string { return 'notice'; }
    public function getFixHint(): string { return 'Add image alt text'; }

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
            if ($this->altStatus($page['body']) === 'missing') {
                $out[] = new Result('product', $entityId, $path, 'Main product image renders with no alt text (no gallery caption and no img alt).', $storeId);
            }
        }
        return $out;
    }

    /** @return string ok | missing | no_image */
    private function altStatus(string $html): string
    {
        // Hyva gallery JSON config carries a per-image caption/label
        if (preg_match('/"(?:caption|label)"\s*:\s*"[^"]+"/i', $html)) {
            return 'ok';
        }

        $hasProductImage = false;
        if (preg_match_all('/<img\b[^>]*>/i', $html, $imgs)) {
            foreach ($imgs[0] as $img) {
                if (!preg_match('/\bsrc\s*=\s*("|\')([^"\']*(?:catalog\/product|\/media\/catalog|res\.cloudinary)[^"\']*)\1/i', $img)) {
                    continue;
                }
                $hasProductImage = true;
                if (preg_match('/\balt\s*=\s*("|\')([^"\']+)\1/i', $img)) {
                    return 'ok';
                }
            }
        }

        // gallery JSON present (images) but no caption matched above => has images, no alt
        if (preg_match('/"(?:img|full|thumb)"\s*:\s*"https?:[^"]*(?:res\.cloudinary|\/media\/catalog)/i', $html)) {
            $hasProductImage = true;
        }

        return $hasProductImage ? 'missing' : 'no_image';
    }
}
