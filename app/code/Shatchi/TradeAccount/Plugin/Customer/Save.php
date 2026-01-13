<?php

namespace Shatchi\TradeAccount\Plugin\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Customer\Api\Data\CustomerExtensionFactory;
use Psr\Log\LoggerInterface;

class Save
{
    protected $request;
    protected $customerRepository;
    protected $customerExtensionFactory;
    protected $logger;

    public function __construct(
        RequestInterface $request,
        CustomerRepositoryInterface $customerRepository,
        CustomerExtensionFactory $customerExtensionFactory,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->customerRepository = $customerRepository;
        $this->customerExtensionFactory = $customerExtensionFactory;
        $this->logger = $logger;
    }

    public function beforeSave(
        CustomerRepositoryInterface $subject,
        CustomerInterface $customer
    ) {
        $customerId = $customer->getId();
        $this->logger->debug("Saving Customer ID: " . $customerId);

        // Fetch custom attributes from request
        $customerData = $this->request->getParam('customer');

        if (!isset($customerData['total_outlets']) || !isset($customerData['customers_message']) || !isset($customerData['customer_website'])) {
            $this->logger->debug("No custom attributes found in request");
            return [$customer];
        }

        $totalOutlets = $customerData['total_outlets'];
        $customersMessage = $customerData['customers_message'];
        $website = $customerData['customer_website'];



        // Ensure extension attributes are initialized
        $extensionAttributes = $customer->getExtensionAttributes();
        if (!$extensionAttributes) {
            $extensionAttributes = $this->customerExtensionFactory->create();
        }

        // Assign custom attributes
        $extensionAttributes->setTotalOutlets($totalOutlets);
        $extensionAttributes->setCustomersMessage($customersMessage);
        $extensionAttributes->setCustomerWebsite($website);
        // Log values
        $this->logger->debug("Saving Total Outlets: " . $totalOutlets);
        $this->logger->debug("Saving Customers Message: " . $customersMessage);
        $this->logger->debug("Saving Website: " . $website);
        // Attach back to customer
        $customer->setExtensionAttributes($extensionAttributes);

        return [$customer];
    }
}
