<?php

namespace Shatchi\CustomerApproval\Plugin;

use Magento\Customer\Model\AccountManagement;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

class CheckCustomerApproval
{
    protected $customerRepository;

    public function __construct(CustomerRepositoryInterface $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public function beforeAuthenticate(AccountManagement $subject, $username, $password)
    {
        // Load customer by email
        $customer = $this->customerRepository->get($username);
        
        // Get approval status
        $approvalStatus = $customer->getCustomAttribute('is_approved') ? $customer->getCustomAttribute('is_approved')->getValue() : 'pending';

        // Allow only 'approved' status to login
        if ($approvalStatus !== 'approved') {
            throw new LocalizedException(__('Your account is not approved yet. Current status: ' . ucfirst($approvalStatus) . '. Please contact support.'));
        }

        return [$username, $password];
    }
}
