<?php
namespace Shatchi\CustomMessages1\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class PopupPosition implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'top-left', 'label' => __('Top Left')],
            ['value' => 'top-center', 'label' => __('Top Center')],
            ['value' => 'top-right', 'label' => __('Top Right')],
            ['value' => 'bottom-left', 'label' => __('Bottom Left')],
            ['value' => 'bottom-center', 'label' => __('Bottom Center')],
            ['value' => 'bottom-right', 'label' => __('Bottom Right')],
        ];
    }
}
