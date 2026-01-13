<?php

namespace Shatchi\SearchFilter3\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Customer\Model\Session;
use Magento\Store\Model\ScopeInterface;


class PriceSlider extends Template
{
    protected $productCollectionFactory;
    protected $registry;
    protected $scopeConfig;
    protected $customerSession;

    public function __construct(
        Context $context,
        CollectionFactory $productCollectionFactory,
        Registry $registry,
        ScopeConfigInterface $scopeConfig,
        Session $customerSession,
        array $data = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->registry = $registry;
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
        parent::__construct($context, $data);
    }

    public function getMaxPrice()
    {
        $category = $this->registry->registry('current_category');

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('price');
        $collection->addAttributeToFilter('status', ['eq' => 1]);
        $collection->addAttributeToFilter('visibility', ['neq' => 1]);

        if ($category && $category->getId()) {
            $collection->addCategoryFilter($category);
        }

        $prices = $collection->getColumnValues('price');

        if (!empty($prices)) {
            $maxPrice = max($prices);
            return $maxPrice !== null ? ceil((float)$maxPrice) : 0;
        }

        return 1000;
    }

    // public function getEnabledFilters(): array
    // {
    //     $isLoggedIn = $this->customerSession->isLoggedIn();

    //     $configPath = $isLoggedIn
    //         ? 'shatchi_settings/search_filter3/filter_visibility/filters_for_logged_in'
    //         : 'shatchi_settings/search_filter3/filter_visibility/filters_for_guests';

    //     $value = $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE);
    //     return array_filter(array_map('trim', explode(',', (string) $value)));
    // }
    public function getEnabledFilters(): array
    {
        $isLoggedIn = $this->customerSession->isLoggedIn();

        $configPath = $isLoggedIn
            ? 'shatchi_settings/search_filter3/filter_visibility/filters_for_logged_in'
            : 'shatchi_settings/search_filter3/filter_visibility/filters_for_guests';

        $value = $this->scopeConfig->getValue($configPath, ScopeInterface::SCOPE_STORE);

        if (empty($value)) {
            return []; // ✅ ensures it's always an array
        }

        if (is_array($value)) {
            return array_filter(array_map('trim', $value));
        }

        return array_filter(array_map('trim', explode(',', (string) $value)));
    }
}
