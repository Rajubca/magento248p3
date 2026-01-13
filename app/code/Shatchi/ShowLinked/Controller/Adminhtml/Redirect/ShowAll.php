<?php

namespace Shatchi\ShowLinked\Controller\Adminhtml\Redirect;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Backend\Model\Session;
use Magento\Framework\Session\SessionManagerInterface;

class ShowAll extends Action
{
    protected $resultRedirectFactory;
    protected $backendSession;
    protected $session;

    public function __construct(
        Action\Context $context,
        RedirectFactory $resultRedirectFactory,
        Session $backendSession,
        SessionManagerInterface $session
    ) {
        parent::__construct($context);
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->backendSession = $backendSession;
        $this->session = $session;
    }

    public function execute()
    {
        // 🔴 Instead of removing filters, set a default "All Customers" filter
        // $defaultFilter = ['is_approved' => ['approved', 'pending', 'new', 'notapproved']];
        $defaultFilter = ['is_approved' => ['approved', 'pending', 'new', 'notapproved']];
        $this->session->setData('customer_grid_filter', $defaultFilter);

        // Debugging log
        error_log("Applied Default Filter for Show All: " . print_r($defaultFilter, true), 3, BP . '/var/log/customer_grid_filter.log');

        // Notify user
        $this->messageManager->addSuccessMessage(__('Showing all customers with default filters.'));

        // Redirect to Customer Grid
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('customer/index/index');

        return $resultRedirect;
    }
}
