<?php
namespace Shatchi\CustomCheckout\Block\Cart;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

class Banner extends Template
{
    public function __construct(
        Template\Context $context,
        private Cart $cart,
        private ScopeConfigInterface $scopeConfig,
        private PriceHelper $priceHelper,
        array $data = []
    ) { parent::__construct($context, $data); }

    private function threshold(): float
    {
        $v = (float)$this->scopeConfig->getValue('carriers/freeshipping/free_shipping_subtotal', ScopeInterface::SCOPE_STORE);
        return $v > 0 ? $v : 500.0;
    }

    private function surchargeAmount(): float { return 50.0; } // configurable later

    private function subtotalEx(): float { return (float)$this->cart->getQuote()->getSubtotal(); }

    public function remaining(): float
    {
        $rem = $this->threshold() - $this->subtotalEx();
        return $rem > 0 ? $rem : 0.0;
    }

    public function appliesSurcharge(): bool
    {
        return $this->subtotalEx() + 0.0001 < $this->threshold();
    }

    public function isFreeShippingActive(): bool
    {
        return (bool)$this->scopeConfig->getValue('carriers/freeshipping/active', ScopeInterface::SCOPE_STORE);
    }

    public function price($amount): string { return $this->priceHelper->currency($amount, true, false); }

    public function getDataForTemplate(): array
    {
        return [
            'threshold' => $this->price($this->threshold()),
            'remaining' => $this->price($this->remaining()),
            'surcharge' => $this->price($this->surchargeAmount()),
            'show_free' => $this->isFreeShippingActive(),
            'apply'     => $this->appliesSurcharge()
        ];
    }
}
