<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model;

use Etechflow\SeoAudit\Api\CheckInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\FlagManager;
use Psr\Log\LoggerInterface;

/**
 * Runs every registered check, replaces the issue table with fresh findings,
 * and persists a summary (counts + score + timestamp) via FlagManager.
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
        private readonly array $checks = []
    ) {
    }

    /**
     * @return array{score:int,total:int,by_severity:array,by_category:array,checks:int,ran_at:string}
     */
    public function scan(?string $ranAt = null): array
    {
        $conn  = $this->resource->getConnection();
        $table = $this->resource->getTableName('etechflow_seoaudit_issue');
        $conn->delete($table);

        $bySeverity = ['critical' => 0, 'warning' => 0, 'notice' => 0];
        $byCategory = [];
        $total = 0;
        $ran   = 0;

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
            $rows = [];
            foreach ($results as $r) {
                $rows[] = [
                    'check_code'  => $check->getCode(),
                    'check_label' => $check->getLabel(),
                    'fix_hint'    => $check->getFixHint(),
                    'category'    => $check->getCategory(),
                    'severity'    => $check->getSeverity(),
                    'entity_type' => $r->entityType,
                    'entity_id'   => $r->entityId,
                    'identifier'  => mb_substr((string) $r->identifier, 0, 255),
                    'detail'      => $r->detail,
                    'store_id'    => $r->storeId,
                ];
            }
            if ($rows) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    $conn->insertMultiple($table, $chunk);
                }
                $sev = $check->getSeverity();
                $bySeverity[$sev] = ($bySeverity[$sev] ?? 0) + count($rows);
                $cat = $check->getCategory();
                $byCategory[$cat] = ($byCategory[$cat] ?? 0) + count($rows);
                $total += count($rows);
            }
        }

        $score = $this->scoreCalculator->calculate($bySeverity);

        $summary = [
            'score'       => $score,
            'total'       => $total,
            'by_severity' => $bySeverity,
            'by_category' => $byCategory,
            'checks'      => $ran,
            'ran_at'      => $ranAt ?? '',
        ];
        $this->flagManager->saveFlag(self::FLAG_SUMMARY, $summary);

        return $summary;
    }

    /** @return array|null */
    public function getLastSummary(): ?array
    {
        $v = $this->flagManager->getFlagData(self::FLAG_SUMMARY);
        return is_array($v) ? $v : null;
    }
}
