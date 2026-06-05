<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Model;

use Magento\Framework\Model\AbstractModel;

class Issue extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Etechflow\SeoAudit\Model\ResourceModel\Issue::class);
    }
}
