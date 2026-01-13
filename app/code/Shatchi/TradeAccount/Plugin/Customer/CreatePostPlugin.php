<?php

namespace Shatchi\TradeAccount\Plugin\Customer;

use Magento\Customer\Controller\Account\CreatePost;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Customer\Model\Session;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class CreatePostPlugin
{
    private $customerRepository;
    private $customerSession;
    private $messageManager;
    private $logger;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        Session $customerSession,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    public function afterExecute(CreatePost $subject, $result)
    {
        $this->logger->info('TradeAccount Plugin: Customer saved plugin CALLED.');
        

        if ($result instanceof Redirect && $subject->getRequest()->isPost()) {
            $this->logger->info('TradeAccount Plugin: Entered IF block');
            try {
                $email = $subject->getRequest()->getParam('email');
                $this->logger->info('TradeAccount Plugin: Email from request', ['email' => $email]);

                if ($email) {
                    $customer = $this->customerRepository->get($email);
                    
                    $this->logger->info('TradeAccount Plugin: Customer fetched', ['customer_id' => $customer->getId()]);

                    $request = $subject->getRequest();
                    $totalOutlets = $request->getParam('total_outlets');
                    $customersMessage = $request->getParam('customers_message');
                    $website = $request->getParam('customer_website');

                    $this->logger->info('TradeAccount Plugin: Retrieved request params.', [
                        'total_outlets' => $totalOutlets,
                        'customers_message' => $customersMessage,
                        'customer_website' => $website,
                        'customer_id' => $customer->getId()
                    ]);

                    $extensionAttributes = $customer->getExtensionAttributes();

                    if ($extensionAttributes) {
                        $extensionAttributes->setTotalOutlets($totalOutlets);
                        $extensionAttributes->setCustomersMessage($customersMessage);
                        $extensionAttributes->setCustomerWebsite($website);
                        $customer->setExtensionAttributes($extensionAttributes);

                        // Log extension attribute data
                        $this->logger->info('TradeAccount Plugin: Set extension attributes.', [
                            'total_outlets' => $extensionAttributes->getTotalOutlets(),
                            'customers_message' => $extensionAttributes->getCustomersMessage(),
                            'customer_website' => $extensionAttributes->getCustomerWebsite()
                        ]);
                    }
                    $this->customerRepository->save($customer);
                    $this->logger->info('TradeAccount Plugin: Customer saved successfully.');
                } else {
                    $this->logger->warning('TradeAccount Plugin: No email in request');
                }
            } catch (\Exception $e) {
                $this->logger->error('TradeAccount Plugin: Error saving custom attributes - ' . $e->getMessage());
                $this->messageManager->addErrorMessage(__('Error saving custom attributes: %1', $e->getMessage()));
            }
        } else {
            $this->logger->warning('TradeAccount Plugin: IF condition failed', [
                'is_redirect' => $result instanceof Redirect,
                'is_post' => $subject->getRequest()->isPost()
            ]);
        }

        return $result;
    }
}
