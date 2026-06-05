<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Turns issue counts into a 0-100 SEO health score. Transparent weighted model:
 * penalty = critical*3 + warning*1 + notice*0.3, normalised against the number
 * of scannable entities (products + categories + CMS pages). Fewer issues per
 * entity = higher score. Clamped to [0,100].
 */
class ScoreCalculator
{
    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /**
     * @param array<string,int> $bySeverity
     */
    public function calculate(array $bySeverity): int
    {
        $penalty = ($bySeverity['critical'] ?? 0) * 3.0
            + ($bySeverity['warning'] ?? 0) * 1.0
            + ($bySeverity['notice'] ?? 0) * 0.3;

        $entities = max(1, $this->entityCount());
        $score = 100 - (int) round(($penalty / $entities) * 100);

        return max(0, min(100, $score));
    }

    protected function entityCount(): int
    {
        $conn = $this->resource->getConnection();
        $products = (int) $conn->fetchOne(
            $conn->select()->from($this->resource->getTableName('catalog_product_entity'), 'COUNT(*)')
        );
        $categories = (int) $conn->fetchOne(
            $conn->select()->from($this->resource->getTableName('catalog_category_entity'), 'COUNT(*)')
        );
        $cms = (int) $conn->fetchOne(
            $conn->select()->from($this->resource->getTableName('cms_page'), 'COUNT(*)')
        );
        return $products + $categories + $cms;
    }
}
