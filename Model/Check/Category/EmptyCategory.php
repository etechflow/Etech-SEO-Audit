<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Category;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

/**
 * Active categories that contain no products. Empty category pages are thin
 * content and a poor landing experience.
 */
class EmptyCategory extends AbstractCheck
{
    public function getCode(): string { return 'category_empty'; }
    public function getLabel(): string { return 'Active categories with no products'; }
    public function getCategory(): string { return 'content'; }
    public function getSeverity(): string { return 'notice'; }
    public function getFixHint(): string { return 'Add products or hide the category'; }

    /** @return Result[] */
    public function run(): array
    {
        $conn = $this->connection();
        $isActive = $this->attributeId('is_active', 'catalog_category');
        $name = $this->attributeId('name', 'catalog_category');
        $select = $conn->select()
            ->from(['e' => $this->table('catalog_category_entity')], ['entity_id'])
            ->joinInner(
                ['ia' => $this->table('catalog_category_entity_int')],
                'ia.entity_id = e.entity_id AND ia.store_id = 0 AND ia.attribute_id = ' . $isActive . ' AND ia.value = 1',
                []
            )
            ->joinLeft(
                ['nm' => $this->table('catalog_category_entity_varchar')],
                'nm.entity_id = e.entity_id AND nm.store_id = 0 AND nm.attribute_id = ' . $name,
                ['name' => 'value']
            )
            ->joinLeft(
                ['ccp' => $this->table('catalog_category_product')],
                'ccp.category_id = e.entity_id',
                []
            )
            ->where('e.level > 1')
            ->where('ccp.category_id IS NULL')
            ->group('e.entity_id')
            ->limit(2000);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $out[] = new Result('category', (int) $r['entity_id'], (string) ($r['name'] ?: $r['entity_id']), 'Category contains no products.');
        }
        return $out;
    }
}
