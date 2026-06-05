<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Product;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

class DuplicateMetaTitle extends AbstractCheck
{
    private const LIMIT = 5000;

    public function getCode(): string { return 'product_duplicate_meta_title'; }
    public function getLabel(): string { return 'Products sharing a duplicate meta title'; }
    public function getCategory(): string { return 'meta'; }
    public function getSeverity(): string { return 'warning'; }
    public function getFixHint(): string { return 'Meta Templates / AI SEO'; }

    /** @return Result[] */
    public function run(): array
    {
        $conn = $this->connection();
        $attr = $this->attributeId('meta_title');
        $dup = $conn->select()
            ->from(['v' => $this->table('catalog_product_entity_varchar')], ['value'])
            ->where('v.attribute_id = ?', $attr)
            ->where('v.store_id = 0')
            ->where('v.value <> ?', '')
            ->group('v.value')
            ->having('COUNT(*) > 1');

        $select = $conn->select()
            ->from(['e' => $this->table('catalog_product_entity')], ['entity_id', 'sku'])
            ->joinInner(
                ['mt' => $this->table('catalog_product_entity_varchar')],
                'mt.entity_id = e.entity_id AND mt.store_id = 0 AND mt.attribute_id = ' . $attr,
                ['value']
            )
            ->joinInner(['dup' => new \Zend_Db_Expr('(' . $dup . ')')], 'dup.value = mt.value', [])
            ->limit(self::LIMIT);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $out[] = new Result('product', (int) $r['entity_id'], (string) $r['sku'], 'Meta title is duplicated: "' . mb_substr((string) $r['value'], 0, 80) . '"');
        }
        return $out;
    }
}
