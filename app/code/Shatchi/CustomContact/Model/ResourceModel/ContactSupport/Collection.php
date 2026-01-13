<?php
namespace Shatchi\CustomContact\Model\ResourceModel\ContactSupport;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Shatchi\CustomContact\Model\ContactSupport as Model;
use Shatchi\CustomContact\Model\ResourceModel\ContactSupport as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct()
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
