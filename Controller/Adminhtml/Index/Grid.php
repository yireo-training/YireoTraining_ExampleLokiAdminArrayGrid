<?php declare(strict_types=1);

namespace YireoTraining\ExampleLokiAdminArrayGrid\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory as ResultPageFactory;

class Grid extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'YireoTraining_ExampleLokiAdminArrayGrid::grid';

    public function __construct(
        Context $context,
        private readonly ResultPageFactory $resultPageFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $title = __('Loki Grid - Yireo Composer Data');

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getLayout()->getUpdate()->addHandle('loki_admin_array_grid');
        $resultPage->setActiveMenu('YireoTraining_ExampleLokiAdminArrayGrid::index');
        $resultPage->addBreadcrumb($title, $title);
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}
