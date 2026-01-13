<?php
namespace Shatchi\SearchFilter3\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\Registry;

class ProductList extends Template
{
    protected $_registry;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->_registry = $registry;
        parent::__construct($context, $data);
    }

    public function getCurrentCategoryId()
    {
        $category = $this->_registry->registry('current_category');
        return $category ? $category->getId() : null;
    }
}
