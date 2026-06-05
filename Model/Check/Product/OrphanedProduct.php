<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Product;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

/**
 * Visible, enabled products not assigned to ANY category. Orphaned products
 * are hard for crawlers and customers to reach and dilute internal linking.
 */
class OrphanedProduct extends AbstractCheck
{
    public function getCode(): string { return 'product_orphaned'; }
    public function getLabel(): string { return 'Visible products not in any category'; }
    public function getCategory(): string { return 'content'; }
    public function getSeverity(): string { return 'warning'; }
    public function getFixHint(): string { return 'Assign to a category'; }

    /** @return Result[] */
    public function run(): array
    {
        $conn = $this->connection();
        $select = $conn->select()
            ->from(['e' => $this->table('catalog_product_entity')], ['entity_id', 'sku'])
            ->joinInner(
                ['st' => $this->table('catalog_product_entity_int')],
                'st.entity_id = e.entity_id AND st.store_id = 0 AND st.attribute_id = ' . $this->attributeId('status') . ' AND st.value = 1',
                []
            )
            ->joinInner(
                ['vi' => $this->table('catalog_product_entity_int')],
                'vi.entity_id = e.entity_id AND vi.store_id = 0 AND vi.attribute_id = ' . $this->attributeId('visibility') . ' AND vi.value IN (2,3,4)',
                []
            )
            ->joinLeft(
                ['ccp' => $this->table('catalog_category_product')],
                'ccp.product_id = e.entity_id',
                []
            )
            ->where('ccp.product_id IS NULL')
            ->group('e.entity_id')
            ->limit(5000);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $out[] = new Result('product', (int) $r['entity_id'], (string) $r['sku'], 'Not assigned to any category.');
        }
        return $out;
    }
}
