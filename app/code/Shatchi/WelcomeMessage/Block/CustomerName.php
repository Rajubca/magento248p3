<?php
namespace Shatchi\WelcomeMessage\Block;

use Magento\Framework\View\Element\Template;
use Magento\Customer\Helper\Session\CurrentCustomer;

class CustomerName extends Template
{
    protected $currentCustomer;

    public function __construct(
        Template\Context $context,
        CurrentCustomer $currentCustomer,
        array $data = []
    ) {
        $this->currentCustomer = $currentCustomer;
        parent::__construct($context, $data);
    }

    public function isLoggedIn()
    {
        return $this->currentCustomer->getCustomerId() !== null;
    }

    public function getCustomerName()
    {
        return $this->currentCustomer->getCustomer()->getFirstname();
    }
}
