<?php
namespace Shatchi\TradingAccount\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Backend\Model\Auth\Session as AdminSession;

class Verify extends Action
{
    protected $customerFactory;
    protected $transportBuilder;
    protected $storeManager;
    protected $messageManager;
    protected $adminSession;

    public function __construct(
        Context $context,
        CustomerFactory $customerFactory,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        ManagerInterface $messageManager,
        AdminSession $adminSession
    ) {
        parent::__construct($context);
        $this->customerFactory = $customerFactory;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->messageManager = $messageManager;
        $this->adminSession = $adminSession;
    }

    public function execute()
    {
        // Check if Admin is Logged In
        if (!$this->adminSession->isLoggedIn()) {
            $this->messageManager->addErrorMessage(__('You must be logged in as an admin to verify customers.'));
            return $this->_redirect('admin/dashboard/index');
        }

        $customerId = $this->getRequest()->getParam('id');

        if ($customerId) {
            $customer = $this->customerFactory->create()->load($customerId);
            if ($customer->getId()) {
                $customer->setCustomAttribute('is_verified', 1);
                $customer->save();

                // Send email to notify customer about verification success
                $this->sendVerificationEmail($customer);

                $this->messageManager->addSuccessMessage(__('Customer has been verified successfully.'));
            }
        }

        return $this->_redirect('customer/index/index');
    }

    private function sendVerificationEmail($customer)
    {
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier('10')//10 //customer_verification_success_template
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $this->storeManager->getStore()->getId()
                ])
                ->setTemplateVars(['customer' => $customer])
                ->setFrom(['email' => 'sales@shatchi.co.uk', 'name' => 'Shatchi Limited'])
                ->addTo($customer->getEmail(), $customer->getFirstname())
                ->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Error sending email: ' . $e->getMessage()));
        }
    }
}
