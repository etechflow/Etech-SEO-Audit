<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\ResourceModel\Issue;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'issue_id';

    protected function _construct(): void
    {
        $this->_init(
            \Etechflow\SeoAudit\Model\Issue::class,
            \Etechflow\SeoAudit\Model\ResourceModel\Issue::class
        );
    }
}
