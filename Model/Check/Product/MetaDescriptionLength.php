<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Product;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

class MetaDescriptionLength extends AbstractCheck
{
    private const LIMIT = 5000;

    public function getCode(): string { return 'product_meta_description_length'; }
    public function getLabel(): string { return 'Products with a meta description that is too short or too long'; }
    public function getCategory(): string { return 'meta'; }
    public function getSeverity(): string { return 'notice'; }
    public function getFixHint(): string { return 'Meta Templates / AI SEO'; }

    /** @return Result[] */
    public function run(): array
    {
        $conn = $this->connection();
        $attr = $this->attributeId('meta_description');
        if (!$attr) {
            return [];
        }
        $min = $this->config->descriptionMin();
        $max = $this->config->descriptionMax();
        $select = $conn->select()
            ->from(['e' => $this->table('catalog_product_entity')], ['entity_id', 'sku'])
            ->joinInner(
                ['md' => $this->table('catalog_product_entity_varchar')],
                "md.entity_id = e.entity_id AND md.store_id = 0 AND md.attribute_id = {$attr} AND md.value <> ''",
                ['value', 'len' => new \Zend_Db_Expr('CHAR_LENGTH(md.value)')]
            )
            ->where('CHAR_LENGTH(md.value) < ? OR CHAR_LENGTH(md.value) > ' . (int) $max, $min)
            ->limit(self::LIMIT);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $len = (int) $r['len'];
            $out[] = new Result('product', (int) $r['entity_id'], (string) $r['sku'], "Meta description is {$len} chars (recommended {$min}-{$max}).");
        }
        return $out;
    }
}
