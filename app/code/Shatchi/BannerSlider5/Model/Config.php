<?php
namespace Shatchi\BannerSlider5\Model;

use Magento\Framework\Model\AbstractModel;

class Config extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Shatchi\BannerSlider5\Model\ResourceModel\Config::class);
    }
}
