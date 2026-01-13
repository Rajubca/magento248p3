<?php
namespace Shatchi\RemoveJs\Plugin;

class TemplatePlugin
{
    public function afterToHtml(\Magento\Framework\View\Element\Template $subject, $result)
    {
        // Filter only frontend output
        if (php_sapi_name() === 'cli') {
            return $result;
        }

        // Remove AddThis + Polyfill scripts from output
        $result = preg_replace(
            [
                '#<script[^>]+addthis_widget\.js[^<]*</script>#i',
                '#<script[^>]+polyfill\.min\.js[^<]*</script>#i'
            ],
            '',
            $result
        );

        return $result;
    }
}
