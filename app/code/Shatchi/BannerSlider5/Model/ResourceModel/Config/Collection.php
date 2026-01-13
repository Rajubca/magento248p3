<?php
namespace Shatchi\BannerSlider5\Model\ResourceModel\Config;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(
            \Shatchi\BannerSlider5\Model\Config::class,
            \Shatchi\BannerSlider5\Model\ResourceModel\Config::class
        );
    }
}
