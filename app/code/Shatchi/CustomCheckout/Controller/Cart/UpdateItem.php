<?php

namespace Shatchi\CustomCheckout\Controller\Cart;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\View\LayoutFactory;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class UpdateItem extends Action
{
    private $cart;
    private $resultJsonFactory;
    private $formKeyValidator;
    private $layoutFactory;
    private $getProductSalableQty;
    private $stockResolver;
    private $storeManager;
    private $priceHelper;
    private $taxHelper;
    private $scopeConfig;

    public function __construct(
        Context $context,
        Cart $cart,
        JsonFactory $resultJsonFactory,
        FormKeyValidator $formKeyValidator,
        LayoutFactory $layoutFactory,
        GetProductSalableQtyInterface $getProductSalableQty,
        StockResolverInterface $stockResolver,
        StoreManagerInterface $storeManager,
        PriceHelper $priceHelper,
        TaxHelper $taxHelper,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->cart = $cart;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->layoutFactory = $layoutFactory;
        $this->getProductSalableQty = $getProductSalableQty;
        $this->stockResolver = $stockResolver;
        $this->storeManager = $storeManager;
        $this->priceHelper = $priceHelper;
        $this->taxHelper = $taxHelper;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $result->setData(['error' => true, 'message' => __('Invalid form key.')]);
        }

        $itemId = (int)$this->getRequest()->getParam('item_id');
        $reqQty = (int)$this->getRequest()->getParam('qty');

        try {
            $quote = $this->cart->getQuote();
            $item  = $quote->getItemById($itemId);
            if (!$item) {
                return $result->setData(['error' => true, 'message' => __('Item not found.')]);
            }

            $product = $item->getProduct();

            // MOQ detection (custom attr + fallbacks)
            $step = (int)($product->getData('min_sell') ?: $product->getData('moq'));
            if ($step < 1) {
                $stockItem = $product->getExtensionAttributes() ? $product->getExtensionAttributes()->getStockItem() : null;
                if ($stockItem && $stockItem->getMinSaleQty()) {
                    $step = (int)$stockItem->getMinSaleQty();
                } elseif ($stockItem && $stockItem->getQtyIncrements()) {
                    $step = (int)$stockItem->getQtyIncrements();
                } else {
                    $step = 1;
                }
            }
            $min  = $step;

            // Resolve stockId and get salable qty
            $websiteCode = $this->storeManager->getWebsite()->getCode();
            $stock = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode);
            $stockId = (int)$stock->getStockId();

            $salable = (float)$this->getProductSalableQty->execute($product->getSku(), $stockId);
            $max = (int)(floor($salable / $step) * $step); // cap to multiple of step

            if ($max < $min) {
                $this->messageManager->addErrorMessage(
                    __('Only %1 available and MOQ is %2. Please remove the item or reduce other quantities.', (int)$salable, $step)
                );
                return $result->setData([
                    'error' => true,
                    'message' => __('Insufficient stock for MOQ.'),
                    'messages_html' => $this->renderMessages(),
                    'free_delivery_html' => $this->buildFreeDeliveryHtml($quote)
                ]);
            }


            // Round request to MOQ multiple, min, then cap by max
            $rounded = (int)max($min, (int)ceil(max(1, $reqQty) / $step) * $step);
            if ($rounded > $max) {
                $rounded = $max;
                $this->messageManager->addNoticeMessage(
                    __('Quantity capped to %1 due to available stock and MOQ %2.', $rounded, $step)
                );
            } elseif ($rounded != $reqQty) {
                $this->messageManager->addNoticeMessage(
                    __('Quantity adjusted to %1 to match MOQ %2.', $rounded, $step)
                );
            }

            $item->setQty($rounded);

            // Force Flat Rate + recollect totals (handles guests, country, etc.)
            $this->applyBestShippingAndRecollect($quote);

            return $result->setData([

                'success'            => true,
                'applied_qty'        => $rounded,
                'max_qty'            => $max,
                'row_subtotal_html'  => $this->buildRowSubtotalHtml($item),
                'messages_html'      => $this->renderMessages(),
                'free_delivery_html' => $this->buildFreeDeliveryHtml($quote),
                'surcharge_html'     => $this->buildSurchargeHtml($quote),
                'surcharge_applies'  => $this->surchargeApplies($quote),
                'banner_html' => $this->buildBannerHtml($quote),
                // 'cart_totals_html' => $this->buildCartTotalsHtml(), // add this field
                'cart_totals_html' => $this->buildCartTotalsHtmlFromLayout(), // new (below)




            ]);
        } catch (\Exception $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    private function buildBannerHtml(\Magento\Quote\Model\Quote $quote): string
    {
        // render the same block/template used at page load
        $layout = $this->layoutFactory->create();
        $block = $layout->createBlock(\Shatchi\CustomCheckout\Block\Cart\Banner::class)
            ->setTemplate('Shatchi_CustomCheckout::cart/banner.phtml');
        return $block->toHtml();
    }


    private function surchargeApplies(\Magento\Quote\Model\Quote $quote): bool
    {
        $threshold = (float)$this->scopeConfig->getValue('carriers/freeshipping/free_shipping_subtotal', ScopeInterface::SCOPE_STORE);
        if ($threshold <= 0) {
            $threshold = 500.0;
        }
        $subtotalEx = (float)$quote->getSubtotal();
        return $subtotalEx + 0.0001 < $threshold;
    }

    private function buildSurchargeHtml(\Magento\Quote\Model\Quote $quote): string
    {
        $threshold = (float)$this->scopeConfig->getValue('carriers/freeshipping/free_shipping_subtotal', ScopeInterface::SCOPE_STORE);
        if ($threshold <= 0) {
            $threshold = 500.0;
        }
        $surcharge = 50.0;
        $subtotalEx = (float)$quote->getSubtotal();
        $remaining  = max(0.0, $threshold - $subtotalEx);

        if ($subtotalEx + 0.0001 < $threshold) {
            return '<div class="shatchi-surcharge notice">'
                . __('Orders under %1 (ex VAT) incur a %2 surcharge (plus VAT).', $this->priceHelper->currency($threshold, true, false), $this->priceHelper->currency($surcharge, true, false))
                . '<div class="avoid">' . __('Add %1 more to avoid this surcharge.', $this->priceHelper->currency($remaining, true, false)) . '</div>'
                . '</div>';
        }
        return '';
    }

    private function buildRowSubtotalHtml(\Magento\Quote\Model\Quote\Item $item): string
    {
        $displayBoth = $this->taxHelper->displayCartBothPrices();
        $displayIncl = $this->taxHelper->displayCartPriceInclTax();
        $displayExcl = $this->taxHelper->displayCartPriceExclTax();

        $rowExcl = (float)$item->getRowTotal();        // excl tax
        $rowIncl = (float)$item->getRowTotalInclTax(); // incl tax

        $html = '';
        if ($displayBoth) {
            $html .= '<span class="cart-price excl"><span class="label">'
                . __('Excl. Tax') . ':</span> <span class="price">'
                . $this->priceHelper->currency($rowExcl, true, false)
                . '</span></span><br/>';
            $html .= '<span class="cart-price incl"><span class="label">'
                . __('Incl. Tax') . ':</span> <span class="price">'
                . $this->priceHelper->currency($rowIncl, true, false)
                . '</span></span>';
        } elseif ($displayIncl) {
            $html .= '<span class="cart-price"><span class="price">'
                . $this->priceHelper->currency($rowIncl, true, false)
                . '</span></span>';
        } else {
            $html .= '<span class="cart-price"><span class="price">'
                . $this->priceHelper->currency($rowExcl, true, false)
                . '</span></span>';
        }
        return $html;
    }
    private function buildCartTotalsHtmlFromLayout(): string
    {
        // Build the same layout the cart page uses so jsLayout['components'] is available
        $layout = $this->layoutFactory->create();
        $update = $layout->getUpdate();

        $update->addHandle('default');
        $update->addHandle('checkout_cart_index');

        $layout->generateXml();
        $layout->generateElements();

        // Canonical cart totals block defined by Magento
        $block = $layout->getBlock('checkout.cart.totals');

        return $block ? $block->toHtml() : '';
    }

    private function applyBestShippingAndRecollect(\Magento\Quote\Model\Quote $quote): void
    {
        if ($quote->isVirtual()) {
            $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
            return;
        }

        // 1) First collect so subtotal reflects the NEW qty
        $quote->setTotalsCollectedFlag(false)->collectTotals();

        $shipping = $quote->getShippingAddress();

        // Ensure a country for guests
        $defaultCountry = (string)$this->scopeConfig->getValue(
            'general/country/default',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) ?: 'GB';
        if (!$shipping->getCountryId()) {
            $shipping->setCountryId($defaultCountry);
        }

        // Free shipping config
        $fsActive    = (bool)$this->scopeConfig->getValue('carriers/freeshipping/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $fsThreshold = (float)$this->scopeConfig->getValue('carriers/freeshipping/free_shipping_subtotal', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($fsThreshold <= 0) {
            $fsThreshold = 500.0; // fallback
        }

        // Use AFTER-discount subtotal (ex-VAT) now that we’ve collected once
        $subtotalForFree = (float)$quote->getSubtotalWithDiscount();

        // 2) Decide the method
        $desiredMethod = ($fsActive && $subtotalForFree + 0.0001 >= $fsThreshold)
            ? 'freeshipping_freeshipping'
            : 'flatrate_flatrate';

        // Collect rates and validate availability (no accidental “upgrade”)
        $shipping->setCollectShippingRates(true)->collectShippingRates();
        $available = [];
        foreach ($shipping->getAllShippingRates() as $rate) {
            $available[$rate->getCode()] = true;
        }
        if (!isset($available[$desiredMethod])) {
            if ($desiredMethod === 'freeshipping_freeshipping' && isset($available['flatrate_flatrate'])) {
                $desiredMethod = 'flatrate_flatrate';
            } elseif ($desiredMethod === 'flatrate_flatrate' && isset($available['flatrate_flatrate'])) {
                // keep flatrate as intended
                $desiredMethod = 'flatrate_flatrate';
            } else {
                $desiredMethod = $shipping->getShippingMethod() ?: $desiredMethod;
            }
        }

        $shipping->setShippingMethod($desiredMethod);

        // 3) Second collect applies shipping immediately
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
    }




    private function renderMessages(): string
    {
        $layout = $this->layoutFactory->create();
        return $layout->createBlock(\Magento\Framework\View\Element\Messages::class)
            ->setTemplate('Magento_Theme::messages.phtml')
            ->toHtml();
    }

    private function buildFreeDeliveryHtml(\Magento\Quote\Model\Quote $quote): string
    {
        $active = (bool)$this->scopeConfig->getValue('carriers/freeshipping/active', ScopeInterface::SCOPE_STORE);
        $threshold = (float)$this->scopeConfig->getValue('carriers/freeshipping/free_shipping_subtotal', ScopeInterface::SCOPE_STORE);
        if ($threshold <= 0) {
            $threshold = 500.0;
        }
        $subtotalEx = (float)$quote->getSubtotal(); // ex. VAT
        $remaining  = max(0.0, $threshold - $subtotalEx);

        if (!$active) {
            return '';
        }

        if ($remaining > 0.0001) {
            return '<div class="shatchi-free-delivery notice">'
                . __('Add %1 more to get free delivery.', $this->priceHelper->currency($remaining, true, false))
                . '</div>';
        }
        return '<div class="shatchi-free-delivery success">' . __('You’ve unlocked free delivery!') . '</div>';
    }
}
