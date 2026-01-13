<?php
namespace Shatchi\SearchFilter3\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class FilterOptions implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'price', 'label' => __('Price Filter')],
            ['value' => 'stock', 'label' => __('Stock Filter')],
            ['value' => 'custom', 'label' => __('Custom Attribute Filter')],
        ];
    }
}
