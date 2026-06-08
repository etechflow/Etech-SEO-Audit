<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model;

use Etechflow\SeoAudit\Api\CheckInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\FlagManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs every registered check, replaces the issue table with fresh findings,
 * and persists a summary (counts + score + per-check score impact). Each finding
 * also gets a resolved frontend URL so a merchant can open the live page and see
 * the exact problem.
 */
class Scanner
{
    public const FLAG_SUMMARY = 'etechflow_seoaudit_summary';

    /**
     * @param CheckInterface[] $checks
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ScoreCalculator $scoreCalculator,
        private readonly FlagManager $flagManager,
        private readonly LoggerInterface $logger,
        private readonly StoreManagerInterface $storeManager,
        private readonly array $checks = []
    ) {
    }

    public function scan(?string $ranAt = null): array
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('etechflow_seoaudit_issue');
        $conn->delete($table);

        $bySeverity = ['critical' => 0, 'warning' => 0, 'notice' => 0];
        $byCategory = [];
        $byCheck    = [];
        $total      = 0;
        $ran        = 0;
        $collected  = [];

        foreach ($this->checks as $check) {
            if (!$check instanceof CheckInterface) {
                continue;
            }
            try {
                $results = $check->run();
            } catch (\Throwable $e) {
                $this->logger->error('Etechflow_SeoAudit: check failed: ' . $check->getCode(), ['exception' => $e->getMessage()]);
                continue;
            }
            $ran++;
            $n = count($results);
            if ($n === 0) {
                continue;
            }
            foreach ($results as $r) {
                $collected[] = [$check, $r];
            }
            $sev = $check->getSeverity();
            $cat = $check->getCategory();
            $bySeverity[$sev]  = ($bySeverity[$sev] ?? 0) + $n;
            $byCategory[$cat]  = ($byCategory[$cat] ?? 0) + $n;
            $total            += $n;
            $byCheck[$check->getCode()] = [
                'code'       => $check->getCode(),
                'label'      => $check->getLabel(),
                'severity'   => $sev,
                'category'   => $cat,
                'fix_hint'   => $check->getFixHint(),
                'count'      => $n,
                'score_gain' => $this->scoreCalculator->pointsFor($n, $sev),
            ];
        }

        $base   = rtrim($this->frontendBaseUrl(), '/');
        $urlMap = $this->buildUrlMap($collected);

        $rows = [];
        foreach ($collected as [$check, $r]) {
            $rows[] = [
                'check_code'   => $check->getCode(),
                'check_label'  => $check->getLabel(),
                'fix_hint'     => $check->getFixHint(),
                'category'     => $check->getCategory(),
                'severity'     => $check->getSeverity(),
                'entity_type'  => $r->entityType,
                'entity_id'    => $r->entityId,
                'identifier'   => mb_substr((string) $r->identifier, 0, 255),
                'detail'       => $r->detail,
                'store_id'     => $r->storeId,
                'frontend_url' => mb_substr((string) ($this->frontendUrl($base, $urlMap, $r) ?? ''), 0, 500),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            $conn->insertMultiple($table, $chunk);
        }

        $score = $this->scoreCalculator->calculate($bySeverity);
        uasort($byCheck, static fn ($a, $b) => $b['score_gain'] <=> $a['score_gain']);

        $summary = [
            'score'       => $score,
            'total'       => $total,
            'by_severity' => $bySeverity,
            'by_category' => $byCategory,
            'by_check'    => array_values($byCheck),
            'checks'      => $ran,
            'ran_at'      => $ranAt ?? '',
        ];
        $this->flagManager->saveFlag(self::FLAG_SUMMARY, $summary);

        return $summary;
    }

    public function getLastSummary(): ?array
    {
        $v = $this->flagManager->getFlagData(self::FLAG_SUMMARY);
        return is_array($v) ? $v : null;
    }

    private function frontendBaseUrl(): string
    {
        try {
            $store = $this->storeManager->getDefaultStoreView();
            return $store ? (string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function defaultStoreId(): int
    {
        try {
            $store = $this->storeManager->getDefaultStoreView();
            return $store ? (int) $store->getId() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** @return array<string,string> "product:ID"/"category:ID" => request_path */
    private function buildUrlMap(array $collected): array
    {
        $pids = [];
        $cids = [];
        foreach ($collected as [$check, $r]) {
            if (!$r->entityId) {
                continue;
            }
            if ($r->entityType === 'product') {
                $pids[(int) $r->entityId] = true;
            } elseif ($r->entityType === 'category') {
                $cids[(int) $r->entityId] = true;
            }
        }
        $map  = [];
        $conn = $this->resource->getConnection();
        $t    = $this->resource->getTableName('url_rewrite');
        $sid  = $this->defaultStoreId();
        foreach (['product' => array_keys($pids), 'category' => array_keys($cids)] as $type => $ids) {
            foreach (array_chunk($ids, 2000) as $chunk) {
                $select = $conn->select()->from($t, ['entity_id', 'request_path'])
                    ->where('entity_type = ?', $type)
                    ->where('redirect_type = ?', 0)
                    ->where('store_id IN (?)', [0, $sid])
                    ->where('entity_id IN (?)', $chunk);
                foreach ($conn->fetchAll($select) as $row) {
                    $key = $type . ':' . $row['entity_id'];
                    if (!isset($map[$key])) {
                        $map[$key] = (string) $row['request_path'];
                    }
                }
            }
        }
        return $map;
    }

    private function frontendUrl(string $base, array $map, object $r): ?string
    {
        if ($base === '') {
            return null;
        }
        $type  = (string) $r->entityType;
        $id    = (int) ($r->entityId ?? 0);
        $ident = (string) $r->identifier;

        if (($type === 'product' || $type === 'category') && $id && isset($map[$type . ':' . $id])) {
            return $base . '/' . ltrim($map[$type . ':' . $id], '/');
        }
        if (str_starts_with($ident, 'http://') || str_starts_with($ident, 'https://')) {
            return $ident;
        }
        if ($ident !== '' && !str_contains($ident, ' ')) {
            return $base . '/' . ltrim($ident, '/');
        }
        return null;
    }
}
