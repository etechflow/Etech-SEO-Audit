<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Product;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

/**
 * Visible products that HAVE a base image but no alt text (image label) on it —
 * the image then leans on a weak fallback. Skips cleanly if the store has no
 * image_label attribute.
 */
class MissingImageAlt extends AbstractCheck
{
    private const LIMIT = 5000;

    public function getCode(): string { return 'product_missing_image_alt'; }
    public function getLabel(): string { return 'Products whose base image has no alt text (image label)'; }
    public function getCategory(): string { return 'content'; }
    public function getSeverity(): string { return 'notice'; }
    public function getFixHint(): string { return 'Add image alt text'; }

    /** @return Result[] */
    public function run(): array
    {
        $conn     = $this->connection();
        $labelId  = $this->attributeId('image_label');
        if (!$labelId) {
            return [];
        }
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
            ->joinInner(
                ['img' => $this->table('catalog_product_entity_varchar')],
                'img.entity_id = e.entity_id AND img.store_id = 0 AND img.attribute_id = ' . $this->attributeId('image') . " AND img.value IS NOT NULL AND img.value <> '' AND img.value <> 'no_selection'",
                []
            )
            ->joinLeft(
                ['lbl' => $this->table('catalog_product_entity_varchar')],
                'lbl.entity_id = e.entity_id AND lbl.store_id = 0 AND lbl.attribute_id = ' . $labelId,
                []
            )
            ->where("lbl.value IS NULL OR lbl.value = ''")
            ->group('e.entity_id')
            ->limit(self::LIMIT);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $out[] = new Result('product', (int) $r['entity_id'], (string) $r['sku'], 'Base image has no alt text (image label is empty).');
        }
        return $out;
    }
}
