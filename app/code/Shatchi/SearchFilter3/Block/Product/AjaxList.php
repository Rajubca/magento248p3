<?php

namespace Shatchi\SearchFilter3\Block\Product;

class AjaxList extends \Magento\Catalog\Block\Product\ListProduct
{
    protected $_customProductCollection = null;

    public function setCustomProductCollection($collection)
    {
        $this->_customProductCollection = $collection;
    }

    public function getLoadedProductCollection()
    {
        if ($this->_customProductCollection) {
            return $this->_customProductCollection;
        }
        return parent::getLoadedProductCollection();
    }
}
