<?php

namespace Shatchi\ShowLinked\Controller\Adminhtml\Redirect;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Backend\Model\Session;
use Magento\Framework\Registry;
use Magento\Framework\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;

class Customer extends Action
{
    protected $resultRedirectFactory;
    protected $backendSession;
    protected $logger;

    public function __construct(
        Context $context, // ✅ FIXED: Corrected Context class
        RedirectFactory $resultRedirectFactory,
        Session $backendSession,
        Registry $registry,
        SessionManagerInterface $session,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->backendSession = $backendSession;
        $this->registry = $registry;
        $this->session = $session;
        $this->logger = $logger;
    }

    public function execute()
    {
        $customerId = $this->getRequest()->getParam('customer_id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($customerId) {
            $filter = ['entity_id' => $customerId];
            $this->backendSession->setData('customer_grid_filter', $filter);

            // Debugging - Log filter data before redirecting
            $this->logger->info('Stored Filter in Session Before Redirect', ['filter' => $filter]);

            // Redirect to customer grid
            $resultRedirect->setPath('customer/index/index', ['customer_id' => $customerId, 'is_redirected' => 1]);
            return $resultRedirect;
        }

        // If no customer_id, show only "new" or "pending" customers
        return $this->_redirect('customer/index/index', ['is_redirected' => 1]);
    }
}
