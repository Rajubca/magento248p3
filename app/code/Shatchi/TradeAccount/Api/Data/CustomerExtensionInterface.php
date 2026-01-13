<?php

namespace Shatchi\TradeAccount\Api\Data;

use Magento\Framework\Api\ExtensionAttributesInterface;

interface CustomerExtensionInterface extends ExtensionAttributesInterface
{
    public function getTotalOutlets();
    public function setTotalOutlets($totalOutlets);
    public function getCustomersMessage();
    public function setCustomersMessage($customersMessage);
    public function getCustomerWebsite();
    public function setCustomerWebsite($customerWebsite);
}