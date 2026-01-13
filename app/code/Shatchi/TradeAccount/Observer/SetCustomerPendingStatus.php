<?php

namespace Shatchi\TradeAccount\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Psr\Log\LoggerInterface;
use Magento\Framework\DataObject;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Customer\Api\CustomerRepositoryInterface;


class SetCustomerPendingStatus implements ObserverInterface
{
    protected $customerResource;
    protected $logger;
    protected $resource;
    protected $shatchi_senderEmail;
    protected $urlBuilder;
    protected $request;
    protected $formKey;
    protected $storeManager;
    protected $scopeConfig;
    protected $countryFactory;
    protected $groupRepository;
    protected $transportBuilder;
    protected $shatchi_senderName;



    public function __construct(
        CustomerResource $customerResource,
        LoggerInterface $logger,                // <-- Make this #2!
        TransportBuilder $transportBuilder,     // <-- Move to #3!
        RequestInterface $request,
        UrlInterface $urlBuilder,
        FormKey $formKey,
        ResourceConnection $resource,
        StoreManagerInterface $storeManager,

        CountryFactory $countryFactory,
        ScopeConfigInterface $scopeConfig,
        GroupRepositoryInterface $groupRepository,
        CustomerRepositoryInterface $customerRepository

    ) {
        $this->customerResource = $customerResource;
        $this->logger = $logger;
        $this->transportBuilder = $transportBuilder;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->formKey = $formKey;
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->countryFactory = $countryFactory;
        $this->groupRepository = $groupRepository;
        $this->customerRepository = $customerRepository;

    }



    public function execute(Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();
        if (!$customer) {
            return;
        }
        // ✅ Force reload to ensure full data (especially address)
        // try {
        //     $customer = $this->customerRepository->getById($customer->getId());
        // } catch (\Exception $e) {
        //     $this->logger->error('❌ Failed to reload customer: ' . $e->getMessage());
        //     return;
        // }

        if ($this->isGridUpdate($customer->getId()) && $customer->getData('is_approved') === 'new') {


            $this->logger->info("✅ Customer ID {$customer->getId()} started. in new Observer");
            // $this->AdminEmailOnce($observer->getEvent()->getCustomer());
            $this->AdminEmailOnce($customer); // ✅ Pass reloaded customer (with full address)

            $this->logger->info("✅ Customer ID {$customer->getId()} Ended. in new Observer");
        }

        // If status is still "new" (has not been updated yet), set to "pending"
        if ($customer->getData('is_approved') === 'new') {
            $customer->setData('is_approved', 'pending');
            try {
                $this->customerResource->save($customer);
                // $this->customerRepository->save($customer);

                $this->logger->info("✅ Customer ID {$customer->getId()} status updated to pending. in new Observer");
            } catch (\Exception $e) {
                $this->logger->error("❌ Failed to update status to pending: " . $e->getMessage());
            }
        }
    }
    private function isGridUpdate($customerId)
    {
        $connection = $this->resource->getConnection();
        $gridTable = $this->resource->getTableName('customer_grid_flat');

        $select = $connection->select()
            ->from($gridTable, 'entity_id')
            ->where('entity_id = ?', $customerId);

        return (bool) $connection->fetchOne($select);
    }

    private function AdminEmailOnce($customer)
    {
        $customerId = $customer->getId();
        // $customer = 
        $connection = $this->resource->getConnection();
        $customerTable = $this->resource->getTableName('customer_entity');
        $customerTextTable = $this->resource->getTableName('customer_entity_text');
        $customerIntTable = $this->resource->getTableName('customer_entity_int');
        $businessGroupTable = $this->resource->getTableName('customer_group'); // Change this if your business group table is different
        // $this->scopeConfig = $scopeConfig;
        // Assign both sender email and sender name from config
        $this->shatchi_senderName = $this->scopeConfig->getValue(
            'shatchi_tradeaccount_email/shatchi_sender_settings/shatchi_sender_name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $this->shatchi_senderEmail = $this->scopeConfig->getValue(
            'shatchi_tradeaccount_email/shatchi_sender_settings/shatchi_sender_email',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        // Get attribute IDs for `customer_website` and `customers_message`
        $selectAttributes = $connection->select()
            ->from($this->resource->getTableName('eav_attribute'), ['attribute_id', 'attribute_code'])
            ->where('attribute_code IN (?)', ['customer_website', 'customers_message', 'total_outlets'])
            ->where('entity_type_id = (SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = "customer")');

        $attributes = $connection->fetchAll($selectAttributes);

        $attributeIds = [];
        foreach ($attributes as $attribute) {
            $attributeIds[$attribute['attribute_code']] = $attribute['attribute_id'];
        }

        $customerWebsite = null;
        $customersMessage = null;
        $totalOutlets = null;
        $businessGroupName = null;
        // Fetch `group_id` directly from `customer_entity`
        $selectGroupId = $connection->select()
            ->from($customerTable, ['group_id'])
            ->where('entity_id = ?', $customerId);

        $businessGroupId = $connection->fetchOne($selectGroupId);

        if (!empty($attributeIds)) {
            // Fetch the old values for `customer_website` and `customers_message`
            $select = $connection->select()
                ->from($customerTextTable, ['attribute_id', 'value'])
                ->where('entity_id = ?', $customerId)
                ->where('attribute_id IN (?)', array_values([$attributeIds['customer_website'], $attributeIds['customers_message']]));

            $results = $connection->fetchAll($select);

            foreach ($results as $result) {
                if ($result['attribute_id'] == $attributeIds['customer_website']) {
                    $customerWebsite = $result['value'];
                } elseif ($result['attribute_id'] == $attributeIds['customers_message']) {
                    $customersMessage = $result['value'];
                }
            }
            // Fetch the old value for `total_outlets` from `customer_entity_int`
            $selectInt = $connection->select()
                ->from($customerIntTable, ['value'])
                ->where('entity_id = ?', $customerId)
                ->where('attribute_id = ?', $attributeIds['total_outlets']);

            $totalOutlets = $connection->fetchOne($selectInt);

            // Fetch business group name if `group_id` exists
            if ($businessGroupId !== null) {
                $selectGroup = $connection->select()
                    ->from($businessGroupTable, ['customer_group_code'])
                    ->where('customer_group_id = ?', $businessGroupId);

                $businessGroupName = $connection->fetchOne($selectGroup);
            }
        }

        $totalOutlets = $this->request->getParam('total_outlets');
        $customerWebsite = $this->request->getParam('customer_website');
        $customersMessage = $this->request->getParam('customers_message');
        $shippingAddress = $customer->getDefaultShippingAddress();
        $this->logger->info('Total Outlets: ' . $totalOutlets);
        $this->logger->info('Customer Website: ' . $customerWebsite);
        $this->logger->info('Customer Message: ' . $customersMessage);

        $formKey = $this->formKey->getFormKey();
        $businessAddress = $shippingAddress ? $shippingAddress->getStreet() : ['N']; // Get street address array
        $customerDataArray = [
            'customer_id' => $customer->getId(),
            'customer_name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
            'customer_email' => $customer->getEmail(),
            'business_name' => $shippingAddress ? $shippingAddress->getCompany() : 'N/A1',
            'business_phone' => $shippingAddress ? $shippingAddress->getTelephone() : 'N/A1',
            'business_address' => $shippingAddress ? implode(', ', $businessAddress) : 'N/A1',
            'business_city' => $shippingAddress ? $shippingAddress->getCity() : 'N/A1',
            'business_postcode' => $shippingAddress ? $shippingAddress->getPostcode() : 'N/A1',
            'business_country' => $this->getCountryName($shippingAddress ? $shippingAddress->getCountryId() : 'GB'),
            'business_type' => $businessGroupName, // Fetch Business Group Nam
            'total_outlets' => $totalOutlets,
            'customer_website' => $customerWebsite,
            'customer_message' => $customersMessage,
            // 'approve_link' => $approvalUrl,
            // 'reject_link' => $rejectionUrl,
            // 'adminCustomerUrl' => $adminCustomerUrl,
        ];

        // $this->logger->info("Approval URL: " . $approvalUrl);
        // $this->logger->info("Rejection URL: " . $rejectionUrl);

        // Your email sending logic here
        $this->logger->info("Customer Data Array: " . json_encode($customerDataArray));

        // ✅ Convert array to DataObject for email compatibility
        $customerDataObject = new DataObject($customerDataArray);

        // 1️⃣ Send Verification Email to Admin
        // $adminEmail = 'partyngiftsuk@gmail.com';
        // $adminEmail = 'sales@magento-1316314-5853199.cloudwaysapps.com';
        $adminEmail = $this->shatchi_senderEmail;
        $verificationLink = $this->urlBuilder->getUrl(
            'admin_141y2y/customer/verify/',
            ['id' => $customer->getId()]
        );
        // Example values - adjust "pending" to your actual status value if needed
        // if ($customerId && $newStatus === "pending") {
        // $this->logger->info("checkCustomerRegisteredOrNot: " . $this->checkCustomerRegisteredOrNot($customer));
        // Do NOT send admin email #10
        //     return;
        // }


        // $adminEmailSent = $this->sendEmail(
        //     $adminEmail,
        //     'Admin',
        //     '10', // ✅ Correct identifier
        //     [
        //         'data' => $customerDataObject, // ✅ Now passes as an object
        //         'verification_link' => $verificationLink
        //     ]
        // );
    }
    private function sendEmail($toEmail, $toName, $templateId, $templateVars)
    {
        try {
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $this->storeManager->getStore()->getId()
                ])
                ->setTemplateVars($templateVars)
                // ->setFrom(['email' => 'sales@magento-1316314-5853199.cloudwaysapps.com', 'name' => 'Shatchi Limited'])
                ->setFrom(['email' => $this->shatchi_senderEmail, 'name' => $this->shatchi_senderName])
                ->addTo($toEmail, $toName)
                ->getTransport();

            $transport->sendMessage();
            $this->logger->info("✅ Email sent to: " . $toEmail);
            return true; // Email sent successfully
        } catch (\Exception $e) {
            $this->logger->error("❌ Email error: " . $e->getMessage());
            return false; // Email sending failed
        }
    }
    public function getCountryName($countryCode)
    {
        if (!$countryCode) {
            return 'N/A';
        }

        $country = $this->countryFactory->create()->loadByCode($countryCode);
        return $country->getName() ?: 'N/A';
    }

    public function getBusinessGroupName($groupId)
    {
        try {
            $group = $this->groupRepository->getById($groupId);
            return $group->getCode(); // Fetches the customer group name
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}
