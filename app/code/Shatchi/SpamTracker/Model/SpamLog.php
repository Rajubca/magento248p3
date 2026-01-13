<?php
namespace Shatchi\SpamTracker\Model;

use Magento\Framework\Model\AbstractModel;

class SpamLog extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Shatchi\SpamTracker\Model\ResourceModel\SpamLog::class);
    }
}