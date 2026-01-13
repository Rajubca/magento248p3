<?php
namespace Shatchi\TradeAccount\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;

class MassDisapprove extends Action
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
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $customerIds = $collection->getAllIds();

            $count = 0;
            foreach ($customerIds as $customerId) {
                $customer = $this->customerRepository->getById($customerId);

                $customer->setCustomAttribute('is_approved', 'notapproved');

                $this->customerRepository->save($customer);
                $count++;
            }

            if ($count === 0) {
                $this->messageManager->addErrorMessage(__('Please select customers.'));
            } else {
                $this->messageManager->addSuccessMessage(
                    __('A total of %1 customer(s) have been disapproved.', $count)
                );
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Something went wrong: %1', $e->getMessage()));
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)
            ->setPath('customer/index/index');
    }
}
