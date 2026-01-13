<?php
namespace Shatchi\WelcomeMessage\Block;

use Magento\Framework\View\Element\Template;

class Header extends Template
{
    protected $_customerSession;

    public function __construct(
        Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        array $data = []
    ) {
        $this->_customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function isLoggedIn()
    {
        return $this->_customerSession->isLoggedIn();
    }

    public function getCustomerFirstName()
    {
        return $this->_customerSession->getCustomer()->getFirstname();
    }
}
