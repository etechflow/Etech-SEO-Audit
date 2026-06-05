<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Product;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

class MissingMetaDescription extends AbstractCheck
{
    private const LIMIT = 5000;

    public function getCode(): string { return 'product_missing_meta_description'; }
    public function getLabel(): string { return 'Products missing a meta description'; }
    public function getCategory(): string { return 'meta'; }
    public function getSeverity(): string { return 'warning'; }
    public function getFixHint(): string { return 'Meta Templates / AI SEO'; }

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
                ['md' => $this->table('catalog_product_entity_varchar')],
                'md.entity_id = e.entity_id AND md.store_id = 0 AND md.attribute_id = ' . $this->attributeId('meta_description'),
                []
            )
            ->where('md.value IS NULL OR md.value = ?', '')
            ->group('e.entity_id')
            ->limit(self::LIMIT);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $out[] = new Result('product', (int) $r['entity_id'], (string) $r['sku'], 'No meta description set.');
        }
        return $out;
    }
}
