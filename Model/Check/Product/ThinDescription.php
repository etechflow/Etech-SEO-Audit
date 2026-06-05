<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Product;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

class ThinDescription extends AbstractCheck
{
    private const LIMIT = 5000;

    public function getCode(): string { return 'product_thin_description'; }
    public function getLabel(): string { return 'Products with thin or missing description content'; }
    public function getCategory(): string { return 'content'; }
    public function getSeverity(): string { return 'notice'; }
    public function getFixHint(): string { return 'AI SEO / manual'; }

    /** @return Result[] */
    public function run(): array
    {
        $conn = $this->connection();
        $thin = $this->config->thinDescription();
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
                ['d' => $this->table('catalog_product_entity_text')],
                'd.entity_id = e.entity_id AND d.store_id = 0 AND d.attribute_id = ' . $this->attributeId('description'),
                ['len' => new \Zend_Db_Expr('CHAR_LENGTH(d.value)')]
            )
            ->where('d.value IS NULL OR CHAR_LENGTH(d.value) < ?', $thin)
            ->group('e.entity_id')
            ->limit(self::LIMIT);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $len = (int) $r['len'];
            $msg = $len === 0 ? 'No description.' : "Description is only {$len} chars (recommended {$thin}+).";
            $out[] = new Result('product', (int) $r['entity_id'], (string) $r['sku'], $msg);
        }
        return $out;
    }
}
