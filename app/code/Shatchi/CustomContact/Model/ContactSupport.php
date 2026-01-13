<?php
namespace Shatchi\CustomContact\Model;

use Magento\Framework\Model\AbstractModel;

class ContactSupport extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Shatchi\CustomContact\Model\ResourceModel\ContactSupport::class);
    }
}
