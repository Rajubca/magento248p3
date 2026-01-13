<?php
namespace Shatchi\CustomCheckout\Block\Cart;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;

class Surcharge extends Template
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

    // You can wire these to custom config later; using sensible defaults for now
    public function getThreshold(): float
    {
        // Reuse Free Shipping threshold if set; fallback £500
        $v = (float)$this->scopeConfig->getValue('carriers/freeshipping/free_shipping_subtotal', ScopeInterface::SCOPE_STORE);
        return $v > 0 ? $v : 500.0;
    }

    public function getAmount(): float
    {
        // Fixed surcharge of £50 (can move to config path later)
        return 50.0;
    }

    public function getSubtotalEx(): float
    {
        return (float)$this->cart->getQuote()->getSubtotal(); // ex VAT
    }

    public function applies(): bool
    {
        return $this->getSubtotalEx() + 0.0001 < $this->getThreshold();
    }

    public function getRemaining(): float
    {
        $rem = $this->getThreshold() - $this->getSubtotalEx();
        return $rem > 0 ? $rem : 0.0;
    }

    public function formatPrice($amount): string
    {
        return $this->priceHelper->currency($amount, true, false);
    }
}
