<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check\Links;

use Etechflow\SeoAudit\Model\Check\AbstractCheck;
use Etechflow\SeoAudit\Model\Check\Result;

/**
 * Detects redirect chains: a redirect whose target is itself the source of
 * another active redirect (A->B, B->C). Chains waste crawl budget and leak
 * link equity — they should point straight to the final URL. Soft dependency
 * on Etechflow_RedirectManager.
 */
class RedirectChain extends AbstractCheck
{
    public function getCode(): string { return 'links_redirect_chain'; }
    public function getLabel(): string { return 'Redirect chains (A→B→C)'; }
    public function getCategory(): string { return 'links'; }
    public function getSeverity(): string { return 'warning'; }
    public function getFixHint(): string { return 'Redirect Manager'; }

    /** @return Result[] */
    public function run(): array
    {
        if (!$this->tableExists('etechflow_redirect')) {
            return [];
        }
        $conn = $this->connection();
        $t = $this->table('etechflow_redirect');
        $select = $conn->select()
            ->from(['a' => $t], ['request_path', 'target_path'])
            ->joinInner(['b' => $t], 'b.request_path = a.target_path AND b.is_active = 1', ['next' => 'target_path'])
            ->where('a.is_active = 1')
            ->limit(500);

        $out = [];
        foreach ($conn->fetchAll($select) as $r) {
            $out[] = new Result(
                'url',
                null,
                (string) $r['request_path'],
                "Chain: {$r['request_path']} → {$r['target_path']} → {$r['next']}. Point it straight to the final URL.",
                0
            );
        }
        return $out;
    }
}
