<?php

namespace Shatchi\TradeAccount\Plugin\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerExtensionInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;


class CustomerPlugin
{
    private $extensionFactory;

    public function __construct(CustomerExtensionInterfaceFactory $extensionFactory)
    {
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Copy extension attributes into custom attributes before save
     */
    public function beforeSave(CustomerRepositoryInterface $subject, CustomerInterface $customer)
    {
        $extensionAttributes = $customer->getExtensionAttributes();
        if ($extensionAttributes) {
            if ($extensionAttributes->getCustomerWebsite() !== null) {
                $customer->setCustomAttribute('customer_website', $extensionAttributes->getCustomerWebsite());
            }
            if ($extensionAttributes->getTotalOutlets() !== null) {
                $customer->setCustomAttribute('total_outlets', $extensionAttributes->getTotalOutlets());
            }
            if ($extensionAttributes->getCustomersMessage() !== null) {
                $customer->setCustomAttribute('customers_message', $extensionAttributes->getCustomersMessage());
            }
        }

        return [$customer];
    }

    /**
     * Load custom attributes into extension attributes after getById
     */
    public function afterGetById(CustomerRepositoryInterface $subject, CustomerInterface $customer)
    {
        return $this->attachExtensionAttributes($customer);
    }

    /**
     * Load custom attributes into extension attributes after getList
     */
    public function afterGetList(CustomerRepositoryInterface $subject, $result)
    {
        foreach ($result->getItems() as $customer) {
            $this->attachExtensionAttributes($customer);
        }
        return $result;
    }

    /**
     * Helper method to attach extension attributes
     */
    private function attachExtensionAttributes(CustomerInterface $customer): CustomerInterface
    {
        $extensionAttributes = $customer->getExtensionAttributes() ?: $this->extensionFactory->create();

        if ($customer->getCustomAttribute('customer_website')) {
            $extensionAttributes->setCustomerWebsite($customer->getCustomAttribute('customer_website')->getValue());
        }
        if ($customer->getCustomAttribute('total_outlets')) {
            $extensionAttributes->setTotalOutlets($customer->getCustomAttribute('total_outlets')->getValue());
        }
        if ($customer->getCustomAttribute('customers_message')) {
            $extensionAttributes->setCustomersMessage($customer->getCustomAttribute('customers_message')->getValue());
        }

        $customer->setExtensionAttributes($extensionAttributes);
        return $customer;
    }
}
