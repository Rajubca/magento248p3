<?php
namespace Shatchi\CustomContact\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Shatchi\CustomContact\Model\ResourceModel\ContactSupport\CollectionFactory;
use Magento\Backend\Block\Template\Context;

class ContactData extends Field
{
    protected $_template = 'Shatchi_CustomContact::system/config/view.phtml';
    protected $collectionFactory;

    public function __construct(
        Context $context,
        CollectionFactory $collectionFactory,
        array $data = []
    ) {
        $this->collectionFactory = $collectionFactory;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function getEntries()
    {
        return $this->collectionFactory->create();
    }

    public function getMediaUrl()
    {
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }
}
