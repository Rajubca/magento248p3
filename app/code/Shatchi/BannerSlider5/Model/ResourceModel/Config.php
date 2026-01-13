<?php
namespace Shatchi\BannerSlider5\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Config extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('shatchi_bannerslider5_config', null); // No primary key
    }
}
