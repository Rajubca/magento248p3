<?php
namespace Shatchi\ShowLinked\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;

class MyForm extends Template
{
    protected $formKey;

    public function __construct(
        Context $context,
        FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->formKey = $formKey;
    }

    public function getFormKey()
    {
        return $this->formKey->getFormKey();
    }
}
