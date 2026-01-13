<?php
namespace Shatchi\TradeAccount\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;

class MassApprove extends Action
{
    /** @var Filter */
    private $filter;

    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    protected function _isAllowed()
    {
        return $this->getRequest()->isPost()
            && $this->_authorization->isAllowed('Magento_Customer::manage');
    }

    public function __construct(
        Action\Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        CustomerRepositoryInterface $customerRepository
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->customerRepository = $customerRepository;
    }

    public function execute()
    {
        try {
            // This handles selected rows AND "Select All" with filters
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $customerIds = $collection->getAllIds();

            $count = 0;
            foreach ($customerIds as $customerId) {
                // Load full customer with all attributes
                $customer = $this->customerRepository->getById($customerId);

                // If 'is_approved' is a customer EAV attribute, use setCustomAttribute
                $customer->setCustomAttribute('is_approved', 'approved');

                // Save through repository so other attributes are not lost
                $this->customerRepository->save($customer);
                $count++;
            }

            if ($count === 0) {
                $this->messageManager->addErrorMessage(__('Please select customers.'));
            } else {
                $this->messageManager->addSuccessMessage(
                    __('A total of %1 customer(s) have been approved.', $count)
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Something went wrong: %1', $e->getMessage()));
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('customer/index/index');
    }
}
