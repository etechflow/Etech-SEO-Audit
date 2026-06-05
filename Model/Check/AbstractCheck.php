<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\Check;

use Etechflow\SeoAudit\Api\CheckInterface;
use Etechflow\SeoAudit\Model\Config;
use Magento\Framework\App\ResourceConnection;

/**
 * Shared plumbing for SQL-backed checks: a DB connection, the config, and a
 * cached product-entity-attribute id lookup. Concrete checks implement run().
 */
abstract class AbstractCheck implements CheckInterface
{
    /** @var array<string,int> */
    private array $attrCache = [];

    public function __construct(
        protected readonly ResourceConnection $resource,
        protected readonly Config $config
    ) {
    }

    protected function connection()
    {
        return $this->resource->getConnection();
    }

    protected function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }

    protected function tableExists(string $name): bool
    {
        return $this->connection()->isTableExists($this->table($name));
    }

    /** Resolve a catalog_product / catalog_category attribute id by code. */
    protected function attributeId(string $code, string $entity = 'catalog_product'): int
    {
        $key = $entity . ':' . $code;
        if (isset($this->attrCache[$key])) {
            return $this->attrCache[$key];
        }
        $conn = $this->connection();
        $select = $conn->select()
            ->from(['a' => $this->table('eav_attribute')], 'attribute_id')
            ->join(['t' => $this->table('eav_entity_type')], 'a.entity_type_id = t.entity_type_id', [])
            ->where('t.entity_type_code = ?', $entity)
            ->where('a.attribute_code = ?', $code)
            ->limit(1);
        return $this->attrCache[$key] = (int) $conn->fetchOne($select);
    }
}
