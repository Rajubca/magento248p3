<?php
namespace Shatchi\TradeAccount\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory as CustomerCollectionFactory;
use Magento\Customer\Model\ResourceModel\Grid\CollectionFactory as CustomerGridCollectionFactory;
use Psr\Log\LoggerInterface;

class SyncCustomAttributes implements ObserverInterface
{
    protected $customerCollectionFactory;
    protected $customerGridCollectionFactory;
    protected $logger;

    public function __construct(
        CustomerCollectionFactory $customerCollectionFactory,
        CustomerGridCollectionFactory $customerGridCollectionFactory,
        LoggerInterface $logger
    ) {
        $this->customerCollectionFactory = $customerCollectionFactory;
        $this->customerGridCollectionFactory = $customerGridCollectionFactory;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            // Load customer collection
            $customerCollection = $this->customerCollectionFactory->create();
            $customerCollection->addAttributeToSelect(['total_outlets', 'customer_message','customer_website']);

            // Load customer grid collection
            $customerGridCollection = $this->customerGridCollectionFactory->create();

            // Sync data
            foreach ($customerCollection as $customer) {
                $customerGrid = $customerGridCollection->getItemById($customer->getId());
                if ($customerGrid) {
                    $customerGrid->setData('total_outlets', $customer->getData('total_outlets'));
                    $customerGrid->setData('customer_message', $customer->getData('customer_message'));
                    $customerGrid->setData('customer_website', $customer->getData('customer_website'));
                    $customerGrid->save();
                }
            }

            $this->logger->info("Custom attributes synced with customer_grid_flat table.");
        } catch (\Exception $e) {
            $this->logger->error("Error syncing custom attributes: " . $e->getMessage());
        }
    }
}