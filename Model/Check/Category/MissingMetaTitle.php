<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Category;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

class MissingMetaTitle extends AbstractCheck
{
    public function getCode(): string { return 'category_missing_meta_title'; }
    public function getLabel(): string { return 'Active categories missing a meta title'; }
    public function getCategory(): string { return 'meta'; }
    public function getSeverity(): string { return 'notice'; }
    public function getFixHint(): string { return 'Meta Templates'; }

    /** @return Result[] */
    public function run(): array
    {
        $conn = $this->connection();
        $isActive = $this->attributeId('is_active', 'catalog_category');
        $metaTitle = $this->attributeId('meta_title', 'catalog_category');
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
                ['mt' => $this->table('catalog_category_entity_varchar')],
                'mt.entity_id = e.entity_id AND mt.store_id = 0 AND mt.attribute_id = ' . $metaTitle,
                []
            )
            ->where('e.level > 1')
            ->where('mt.value IS NULL OR mt.value = ?', '')
            ->group('e.entity_id')
            ->limit(2000);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $out[] = new Result('category', (int) $r['entity_id'], (string) ($r['name'] ?: $r['entity_id']), 'No meta title set.');
        }
        return $out;
    }
}
