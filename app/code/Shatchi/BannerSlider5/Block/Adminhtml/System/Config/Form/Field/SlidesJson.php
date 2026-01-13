<?php
namespace Shatchi\BannerSlider5\Block\Adminhtml\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Shatchi\BannerSlider5\Model\ResourceModel\Config\CollectionFactory;

class SlidesJson extends Field
{
    protected $_template = 'Shatchi_BannerSlider5::system/config/slides_form.phtml';
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
        $slides = $this->collectionFactory->create()->getFirstItem()->getData('slides_json') ?? '[]';
        $this->addData(['slides_json' => $slides]);
        return $this->_toHtml();
    }
}
