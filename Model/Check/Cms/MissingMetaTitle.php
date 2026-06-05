<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Cms;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

class MissingMetaTitle extends AbstractCheck
{
    public function getCode(): string { return 'cms_missing_meta_title'; }
    public function getLabel(): string { return 'Active CMS pages missing a meta title'; }
    public function getCategory(): string { return 'meta'; }
    public function getSeverity(): string { return 'notice'; }
    public function getFixHint(): string { return 'Meta Templates'; }

    /** @return Result[] */
    public function run(): array
    {
        $conn = $this->connection();
        $select = $conn->select()
            ->from(['p' => $this->table('cms_page')], ['page_id', 'title', 'identifier'])
            ->where('p.is_active = 1')
            ->where('p.meta_title IS NULL OR p.meta_title = ?', '')
            ->limit(2000);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $label = (string) ($r['title'] ?: $r['identifier']);
            $out[] = new Result('cms_page', (int) $r['page_id'], $label, 'No meta title set.');
        }
        return $out;
    }
}
