<?php
namespace Shatchi\SpamTracker\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SpamLog extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('spam_logs', 'id');
    }
}