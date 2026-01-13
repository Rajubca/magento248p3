<?php

namespace Shatchi\SearchFilter3\Block\Product;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ProductList3 extends Template
{
    protected $scopeConfig;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Get allowed per-page values from admin config
     * e.g., "12,24,36" => [12, 24, 36]
     */
    public function getPerPageOptions(): array
    {
        $config = $this->scopeConfig->getValue(
            'shatchi_settings/search_filter3/per_page_values',
            ScopeInterface::SCOPE_STORE
        );

        $values = array_filter(array_map('trim', explode(',', (string)$config)));

        return array_map('intval', $values ?: [12, 24, 36]); // Fallback to default
    }

    /**
     * Get number of products per row
     */
    public function getProductsPerRow(): int
    {
        $value = $this->scopeConfig->getValue(
            'shatchi_settings/search_filter3/per_row_products',
            ScopeInterface::SCOPE_STORE
        );

        return (int)$value > 0 ? (int)$value : 5; // Default to 4 if unset or invalid
    }

    public function getUrlEncoder()
    {
        return \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Magento\Framework\Url\EncoderInterface::class);
    }
}
