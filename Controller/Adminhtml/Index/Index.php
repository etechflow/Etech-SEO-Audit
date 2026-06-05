<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    const ADMIN_RESOURCE = 'Etechflow_SeoAudit::audit';

    public function __construct(Context $context, private readonly PageFactory $pageFactory)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Etechflow_SeoAudit::audit');
        $page->getConfig()->getTitle()->prepend(__('SEO Audit'));
        return $page;
    }
}
