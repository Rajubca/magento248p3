<?php
namespace Shatchi\CustomCheckout\Block\Cart;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

class FreeDelivery extends Template
{
    private $cart;
    private $scopeConfig;
    private $priceHelper;

    public function __construct(
        Template\Context $context,
        Cart $cart,
        ScopeConfigInterface $scopeConfig,
        PriceHelper $priceHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->cart = $cart;
        $this->scopeConfig = $scopeConfig;
        $this->priceHelper = $priceHelper;
    }

    public function isActive(): bool
    {
        return (bool)$this->scopeConfig->getValue('carriers/freeshipping/active', ScopeInterface::SCOPE_STORE);
    }

    public function getThreshold(): float
    {
        $v = (float)$this->scopeConfig->getValue('carriers/freeshipping/free_shipping_subtotal', ScopeInterface::SCOPE_STORE);
        return $v > 0 ? $v : 500.0;
    }

    public function getRemaining(): float
    {
        $quote = $this->cart->getQuote();
        $subtotalEx = (float)$quote->getSubtotal(); // ex. VAT
        $remaining = $this->getThreshold() - $subtotalEx;
        return $remaining > 0 ? $remaining : 0.0;
    }

    public function formatPrice($amount): string
    {
        return $this->priceHelper->currency($amount, true, false);
    }
}
