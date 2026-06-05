<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Issue extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('etechflow_seoaudit_issue', 'issue_id');
    }
}
