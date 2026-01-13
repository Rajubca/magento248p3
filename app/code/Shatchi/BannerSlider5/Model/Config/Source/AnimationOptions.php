<?php
namespace Shatchi\BannerSlider5\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class AnimationOptions implements ArrayInterface
{
    public function toOptionArray()
    {
        return [
            ['value' => 'fade', 'label' => __('Fade')],
            ['value' => 'slide', 'label' => __('Slide')],
            // ['value' => 'zoom', 'label' => __('Zoom')],
        ];
    }
}
