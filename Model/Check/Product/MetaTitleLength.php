<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Product;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

class MetaTitleLength extends AbstractCheck
{
    private const LIMIT = 5000;

    public function getCode(): string { return 'product_meta_title_length'; }
    public function getLabel(): string { return 'Products with a meta title that is too short or too long'; }
    public function getCategory(): string { return 'meta'; }
    public function getSeverity(): string { return 'notice'; }
    public function getFixHint(): string { return 'Meta Templates / AI SEO'; }

    /** @return Result[] */
    public function run(): array
    {
        $conn = $this->connection();
        $min = $this->config->titleMin();
        $max = $this->config->titleMax();
        $select = $conn->select()
            ->from(['e' => $this->table('catalog_product_entity')], ['entity_id', 'sku'])
            ->joinInner(
                ['mt' => $this->table('catalog_product_entity_varchar')],
                'mt.entity_id = e.entity_id AND mt.store_id = 0 AND mt.attribute_id = ' . $this->attributeId('meta_title') . " AND mt.value <> ''",
                ['value', 'len' => new \Zend_Db_Expr('CHAR_LENGTH(mt.value)')]
            )
            ->where('CHAR_LENGTH(mt.value) < ? OR CHAR_LENGTH(mt.value) > ' . (int) $max, $min)
            ->limit(self::LIMIT);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $len = (int) $r['len'];
            $out[] = new Result('product', (int) $r['entity_id'], (string) $r['sku'], "Meta title is {$len} chars (recommended {$min}-{$max}).");
        }
        return $out;
    }
}
