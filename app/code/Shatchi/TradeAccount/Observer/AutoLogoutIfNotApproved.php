<?php

namespace Shatchi\TradeAccount\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ActionFlag;
use Magento\Customer\Api\CustomerRepositoryInterface;

class AutoLogoutIfNotApproved implements ObserverInterface
{
    protected $customerSession;
    protected $messageManager;
    protected $redirect;
    protected $actionFlag;
    protected $customerRepository;

    public function __construct(
        CustomerSession $customerSession,
        ManagerInterface $messageManager,
        RedirectInterface $redirect,
        ActionFlag $actionFlag,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->customerSession = $customerSession;
        $this->messageManager = $messageManager;
        $this->redirect = $redirect;
        $this->actionFlag = $actionFlag;
        $this->customerRepository = $customerRepository;
    }

    public function execute(Observer $observer)
    {
        if ($this->customerSession->isLoggedIn()) {
            try {
                $customerId = $this->customerSession->getCustomerId();
                $customer = $this->customerRepository->getById($customerId);
                $approval = $customer->getCustomAttribute('is_approved') ?
                    $customer->getCustomAttribute('is_approved')->getValue() : null;

                if ($approval !== 'approved') {
                    $this->customerSession->logout();
                    $this->messageManager->addNoticeMessage(__('Your account is not approved yet.'));
                    $this->actionFlag->set('', \Magento\Framework\App\ActionInterface::FLAG_NO_DISPATCH, true);
                    $this->redirect->redirect($observer->getControllerAction()->getResponse(), 'customer/account/login');
                }
            } catch (\Exception $e) {
                // Fail silently or log if needed
            }
        }
    }
}
