<?php

namespace Shatchi\ShowLinked\Plugin\Customer;

use Magento\Framework\Session\SessionManagerInterface;
// use Magento\Backend\Model\Session;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Customer\Ui\Component\DataProvider;
use Magento\Customer\Model\ResourceModel\Grid\Collection as CustomerCollection; // Correct type for $searchResult
use Magento\Framework\Registry;
use Magento\Framework\App\RequestInterface;

class MyGrid
{
    protected $backendSession;
    protected $filterBuilder;
    protected $searchCriteriaBuilder;
    protected $request;

    public function __construct(
        SessionManagerInterface $backendSession,
        FilterBuilder $filterBuilder,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Registry $registry,
        RequestInterface $request
    ) {
        $this->backendSession = $backendSession;
        $this->filterBuilder = $filterBuilder;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->registry = $registry;
        $this->request = $request;
    }


    /**
     * Apply search criteria filter to UI component.
     */
    public function beforeGetSearchCriteria(DataProvider $subject)
    {
        $filter = $this->backendSession->getData('customer_grid_filter');
        // $showAll = $this->request->getParam('show_all'); // Custom flag


        // If show_all=1 is passed, clear session filters and return an empty filter array (show all customers)
        // if ($showAll) {
        //     $this->backendSession->unsetData('customer_grid_filter');
        //     // $this->logger->info('Clearing all filters for Show All Customers');
        //     return []; // ✅ Correctly removes filters
        // } 
        

        if ($filter && isset($filter['entity_id'])) {
            error_log("\n Customer Id :  " . print_r($filter['entity_id'], true), 3, BP . '/var/log/customer_grid_filter.log');

            // Create filter for Customer ID
            $customerIdFilter = $this->filterBuilder
                ->setField('entity_id')
                ->setValue($filter['entity_id'])
                ->setConditionType('eq')
                ->create();

            // Apply search criteria filter
            $subject->addFilter($customerIdFilter);

            // Debugging: Log filter application
            error_log("Applying UI Filter: " . print_r($filter, true), 3, BP . '/var/log/customer_grid_filter.log');

            // Ensure session is retained after applying
            $this->backendSession->setData('customer_grid_filter', $filter);
            return [];
        } 
        if($filter && isset($filter['is_approved1'])) {
            error_log("\n Only for new and pending :  " . print_r($filter['is_approved1'], true), 3, BP . '/var/log/customer_grid_filter.log');

            $approvalFilter = $this->filterBuilder
                ->setField('is_approved')
                ->setValue(['new', 'pending'])
                ->setConditionType('in')
                ->create();
            $subject->addFilter($approvalFilter);
            $this->backendSession->setData('customer_grid_filter', $filter);
            return [];
        }
        if ($filter && isset($filter['is_approved'])) {
            // Debugging: Log session data at this point
            error_log("\nShowAll :  " . print_r($filter['is_approved'], true), 3, BP . '/var/log/customer_grid_filter.log');

            // 🔴 Set default "All Customers" filter if Show All is clicked
            // $defaultFilter = ['is_approved' => ['approved', 'pending', 'new', 'notapproved']];
            $defaultFilter = ['is_approved' => ['approved', 'pending', 'new', 'notapproved']];

            // Apply filter to UI Grid
            $approvalFilter = $this->filterBuilder
                ->setField('is_approved')
                ->setValue(['approved', 'pending', 'new', 'notapproved'])
                ->setConditionType('in')
                ->create();
            $subject->addFilter($approvalFilter);

            $this->backendSession->setData('customer_grid_filter', $defaultFilter);
            // $this->logger->info('Applying Default Filter for Show All Customers', ['is_approved' => ['approved', 'pending', 'new', 'rejected']]);
            return [];
        }
    }
}
