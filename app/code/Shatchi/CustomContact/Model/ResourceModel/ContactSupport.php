<?php
namespace Shatchi\CustomContact\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ContactSupport extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('contact_support', 'id');
    }
}
