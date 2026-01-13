<?php

namespace Shatchi\RemoveJs\Plugin;

class LayoutPlugin
{
    public function afterToHtml(\Magento\Framework\View\Element\Template $subject, $result)
    {
        if ($subject->getNameInLayout() === 'head.additional') {
            // Remove AddThis script
            $result = preg_replace('#<script[^>]*s7\.addthis\.com[^<]*</script>#i', '', $result);

            // Remove polyfill script
            $result = preg_replace('#<script[^>]*polyfill\.io[^<]*</script>#i', '', $result);
        }
        return $result;
    }
}
