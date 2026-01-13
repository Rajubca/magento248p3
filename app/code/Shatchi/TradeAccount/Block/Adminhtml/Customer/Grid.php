<?php
namespace Shatchi\TradeAccount\Block\Adminhtml\Customer;

use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Customer\Model\CustomerFactory;

class Grid extends Extended
{
    protected $_customerFactory;
    protected $_customerCollectionFactory;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        CustomerFactory $customerFactory,
        CollectionFactory $customerCollectionFactory,
        array $data = []
    ) {
        $this->_customerFactory = $customerFactory;
        $this->_customerCollectionFactory = $customerCollectionFactory;
        parent::__construct($context, $backendHelper, $data);
    }

    protected function _construct()
    {
        parent::_construct();
        $this->setId('customerGrid');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $customerId = $this->getRequest()->getParam('customer_id');
        if (!$customerId) {
            $customerId = 1; // Default ID if none provided
        }

        $collection = $this->_customerCollectionFactory->create();
        $collection->addFieldToFilter('entity_id', $customerId);
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn(
            'entity_id',
            [
                'header' => __('ID'),
                'index' => 'entity_id',
                'type' => 'number',
            ]
        );

        $this->addColumn(
            'name',
            [
                'header' => __('Name'),
                'index' => 'name',
            ]
        );

        $this->addColumn(
            'email',
            [
                'header' => __('Email'),
                'index' => 'email',
            ]
        );

        return parent::_prepareColumns();
    }
}
