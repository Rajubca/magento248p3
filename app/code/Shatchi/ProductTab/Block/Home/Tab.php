<?php

namespace Shatchi\ProductTab\Block\Home;

use Magento\Framework\View\Element\Template;

class Tab extends Template
{
    /**
     * Get the list of tabs with their enabled/disabled status
     *
     * @return array
     */
    public function getTabsConfig()
    {
        // Example configuration: true = enabled, false = disabled
        return [
            'christmas' => [
                'id' => 'christmas-tab',
                'label' => 'Christmas',
                'template' => 'Shatchi_ProductTab::home/christmas.phtml',
                'enabled' => false // Example: disable this tab
            ],
            'occasion' => [
                'id' => 'occasion-tab',
                'label' => 'Occasion',
                'template' => 'Shatchi_ProductTab::home/occasions.phtml',
                'enabled' => false
            ],
            'outdoors' => [
                'id' => 'outdoors-tab',
                'label' => 'Outdoors',
                'template' => 'Shatchi_ProductTab::home/outdoors.phtml',
                'enabled' => true
            ],
            'party' => [
                'id' => 'party-tab',
                'label' => 'Party',
                'template' => 'Shatchi_ProductTab::home/party.phtml',
                'enabled' => true
            ],
            'gifts' => [
                'id' => 'gifts-tab',
                'label' => 'Gifts',
                'template' => 'Shatchi_ProductTab::home/gifts.phtml',
                'enabled' => true
            ]
        ];
    }
    public function isModuleEnabled()
    {
        return $this->_scopeConfig->isSetFlag(
            'shatchi_producttab/general/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
