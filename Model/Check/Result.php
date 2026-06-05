<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check;

/**
 * A single finding produced by a check.
 */
class Result
{
    public function __construct(
        public readonly string $entityType,
        public readonly ?int $entityId,
        public readonly string $identifier,
        public readonly string $detail,
        public readonly int $storeId = 0
    ) {
    }
}
