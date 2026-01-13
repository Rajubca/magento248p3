<?php

namespace Shatchi\ShowLinked\Controller\Adminhtml\Redirect;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Backend\Model\Session;
use Magento\Framework\Registry;
use Magento\Framework\Session\SessionManagerInterface;


class Customer extends Action
{
    protected $resultRedirectFactory;
    protected $backendSession;

    public function __construct(
        Context $context,
        RedirectFactory $resultRedirectFactory,
        Session $backendSession,
        Registry $registry,
        SessionManagerInterface $session
    ) {
        parent::__construct($context);
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->backendSession = $backendSession;
        $this->registry = $registry;
        $this->session = $session;
    }

    public function execute()
    {
        $customerId = $this->getRequest()->getParam('customer_id');

        if ($customerId) {
            // Store filter in session
            $filter = ['entity_id' => $customerId];
            $this->session->setData('customer_grid_filter', $filter);

            // Debugging - Log filter data before redirecting
            error_log("Stored Filter in Session Before Redirect: " . print_r($this->session->getData('customer_grid_filter'), true), 3, BP . '/var/log/customer_grid_filter.log');

            // Redirect to customer grid
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/index/index'); // Ensure this is correct
            return $resultRedirect;
        } else {
            $defaultFilter = ['is_approved1' => ['pending', 'new']];
            $this->session->setData('customer_grid_filter', $defaultFilter);
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/index/index'); // Ensure this is correct
            return $resultRedirect;
        }

        $this->messageManager->addErrorMessage(__('Customer ID is missing.'));
        return $this->_redirect('customer/index/index');
    }
}
