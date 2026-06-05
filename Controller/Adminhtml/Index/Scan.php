<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Controller\Adminhtml\Index;

use Etechflow\SeoAudit\Model\Scanner;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Scan extends Action
{
    const ADMIN_RESOURCE = 'Etechflow_SeoAudit::scan';

    public function __construct(Context $context, private readonly Scanner $scanner)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        try {
            $s = $this->scanner->scan();
            $this->messageManager->addSuccessMessage(
                __('SEO audit complete — score %1/100, %2 issue(s) across %3 checks.', $s['score'], $s['total'], $s['checks'])
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('SEO audit failed: %1', $e->getMessage()));
        }
        return $redirect->setPath('seoaudit/index/index');
    }
}
