<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Links;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

/**
 * Surfaces logged 404 hits from Etechflow_RedirectManager's 404 catcher.
 * Soft dependency — returns nothing if that module/table isn't installed.
 */
class Broken404 extends AbstractCheck
{
    public function getCode(): string { return 'links_broken_404'; }
    public function getLabel(): string { return 'URLs returning 404 (logged hits)'; }
    public function getCategory(): string { return 'links'; }
    public function getSeverity(): string { return 'critical'; }
    public function getFixHint(): string { return 'Redirect Manager'; }

    /** @return Result[] */
    public function run(): array
    {
        if (!$this->tableExists('etechflow_redirect_404_log')) {
            return [];
        }
        $conn = $this->connection();
        $select = $conn->select()
            ->from($this->table('etechflow_redirect_404_log'), ['request_path', 'hits', 'store_id'])
            ->order('hits DESC')
            ->limit(500);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $hits = (int) ($r['hits'] ?? 0);
            $out[] = new Result(
                'url',
                null,
                (string) $r['request_path'],
                "404 — hit {$hits} time(s). Create a redirect to a live page.",
                (int) ($r['store_id'] ?? 0)
            );
        }
        return $out;
    }
}
