<?php

namespace Shatchi\TradeAccount\Model;

use Magento\Framework\Api\AbstractSimpleObject;
use Shatchi\TradeAccount\Api\Data\CustomerExtensionInterface;

class CustomerExtension extends AbstractSimpleObject implements CustomerExtensionInterface
{
    const TOTAL_OUTLETS = 'total_outlets';
    const CUSTOMERS_MESSAGE = 'customers_message';
    const CUSTOMER_WEBSITE = 'customer_website';

    public function getTotalOutlets()
    {
        return $this->_get(self::TOTAL_OUTLETS);
    }

    public function setTotalOutlets($totalOutlets)
    {
        return $this->setData(self::TOTAL_OUTLETS, $totalOutlets);
    }
    
    public function getCustomerWebsite()
    {
        return $this->_get(self::CUSTOMER_WEBSITE);
    }

    public function setCustomerWebsite($website)
    {
        return $this->setData(self::CUSTOMER_WEBSITE, $website);
    }

    public function getCustomersMessage()
    {
        return $this->_get(self::CUSTOMERS_MESSAGE);
    }

    public function setCustomersMessage($customersMessage)
    {
        return $this->setData(self::CUSTOMERS_MESSAGE, $customersMessage);
    }
}