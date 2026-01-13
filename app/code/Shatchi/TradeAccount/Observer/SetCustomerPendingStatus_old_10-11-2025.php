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
    protected $customerRepository;

    public function __construct(
        CustomerResource $customerResource,
        LoggerInterface $logger,
        TransportBuilder $transportBuilder,
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

        $customerId = $customer->getId();
        
        // ✅ Layer 1: Static variable for current request
        static $processedCustomers = [];
        if (in_array($customerId, $processedCustomers)) {
            $this->logger->info("🛑 Already processed customer ID in this request: " . $customerId);
            return;
        }
        $processedCustomers[] = $customerId;
        
        $this->logger->info("🎯 Processing customer ID: " . $customerId . " with status: " . $customer->getData('is_approved'));

        if ($this->isGridUpdate($customerId) && $customer->getData('is_approved') === 'new') {
            $this->logger->info("✅ Customer ID {$customerId} started email process.");
            $this->AdminEmailOnce($customer);
            $this->logger->info("✅ Customer ID {$customerId} completed email process.");
        }

        // If status is still "new" (has not been updated yet), set to "pending"
        if ($customer->getData('is_approved') === 'new') {
            $customer->setData('is_approved', 'pending');
            try {
                $this->customerResource->save($customer);
                $this->logger->info("✅ Customer ID {$customerId} status updated to pending.");
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

    private function validateCustomerData($customerDataArray)
    {
        $requiredFields = [
            'customer_name',
            'customer_email', 
            'business_type'
        ];
        
        foreach ($requiredFields as $field) {
            if (empty($customerDataArray[$field]) || $customerDataArray[$field] === 'N/A') {
                $this->logger->warning("⚠️ Missing required field: {$field} for customer ID: " . $customerDataArray['customer_id']);
                return false;
            }
        }
        
        return true;
    }

    private function AdminEmailOnce($customer)
    {
        $customerId = $customer->getId();
        $lockDir = BP . '/var/locks/';
        $lockFile = $lockDir . 'customer_email_' . $customerId . '.lock';
        
        // ✅ Ensure lock directory exists with proper permissions
        if (!is_dir($lockDir)) {
            if (!mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
                $this->logger->error("❌ Failed to create lock directory: " . $lockDir);
                return;
            }
        }
        
        // ✅ Check if lock file exists (email already sent)
        if (file_exists($lockFile)) {
            $this->logger->info("🛑 Admin email already sent (lock file exists) for customer ID: " . $customerId);
            return;
        }
        
        // ✅ Create temporary processing lock to prevent concurrent execution
        $tempLockFile = $lockDir . 'customer_email_' . $customerId . '.processing';
        if (file_exists($tempLockFile)) {
            $this->logger->info("🛑 Admin email already being processed for customer ID: " . $customerId);
            return;
        }
        
        try {
            // Create processing lock
            file_put_contents($tempLockFile, time());
            
            // Your existing email preparation logic...
            $connection = $this->resource->getConnection();
            $customerTable = $this->resource->getTableName('customer_entity');
            $customerTextTable = $this->resource->getTableName('customer_entity_text');
            $customerIntTable = $this->resource->getTableName('customer_entity_int');
            $businessGroupTable = $this->resource->getTableName('customer_group');

            $this->shatchi_senderName = $this->scopeConfig->getValue(
                'shatchi_tradeaccount_email/shatchi_sender_settings/shatchi_sender_name',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );

            $this->shatchi_senderEmail = $this->scopeConfig->getValue(
                'shatchi_tradeaccount_email/shatchi_sender_settings/shatchi_sender_email',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );

            // Get attribute IDs
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
            
            // Fetch group_id
            $selectGroupId = $connection->select()
                ->from($customerTable, ['group_id'])
                ->where('entity_id = ?', $customerId);

            $businessGroupId = $connection->fetchOne($selectGroupId);

            if (!empty($attributeIds)) {
                // Fetch attribute values
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
                
                $selectInt = $connection->select()
                    ->from($customerIntTable, ['value'])
                    ->where('entity_id = ?', $customerId)
                    ->where('attribute_id = ?', $attributeIds['total_outlets']);

                $totalOutlets = $connection->fetchOne($selectInt);

                if ($businessGroupId !== null) {
                    $selectGroup = $connection->select()
                        ->from($businessGroupTable, ['customer_group_code'])
                        ->where('customer_group_id = ?', $businessGroupId);

                    $businessGroupName = $connection->fetchOne($selectGroup);
                }
            }

            // Use database values first, then request parameters as fallback
            $totalOutlets = $totalOutlets ?: $this->request->getParam('total_outlets');
            $customerWebsite = $customerWebsite ?: $this->request->getParam('customer_website');
            $customersMessage = $customersMessage ?: $this->request->getParam('customers_message');
            
            $shippingAddress = $customer->getDefaultShippingAddress();
            
            $this->logger->info('Total Outlets: ' . $totalOutlets);
            $this->logger->info('Customer Website: ' . $customerWebsite);
            $this->logger->info('Customer Message: ' . $customersMessage);

            // ✅ FIX: Use proper fallback values for address
            $businessAddress = $shippingAddress ? $shippingAddress->getStreet() : ['N/A'];
            $countryCode = $shippingAddress ? $shippingAddress->getCountryId() : null;
            
            $customerDataArray = [
                'customer_id' => $customer->getId(),
                'customer_name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                'customer_email' => $customer->getEmail(),
                'business_name' => $shippingAddress ? $shippingAddress->getCompany() : 'N/A',
                'business_phone' => $shippingAddress ? $shippingAddress->getTelephone() : 'N/A',
                'business_address' => $shippingAddress ? implode(', ', $businessAddress) : 'N/A',
                'business_city' => $shippingAddress ? $shippingAddress->getCity() : 'N/A',
                'business_postcode' => $shippingAddress ? $shippingAddress->getPostcode() : 'N/A',
                'business_country' => $this->getCountryName($countryCode), // ✅ Fixed country code issue
                'business_type' => $businessGroupName,
                'total_outlets' => $totalOutlets,
                'customer_website' => $customerWebsite,
                'customer_message' => $customersMessage,
            ];

            $this->logger->info("Customer Data Array: " . json_encode($customerDataArray));

            $customerDataObject = new DataObject($customerDataArray);

            $adminEmail = $this->shatchi_senderEmail;
            $verificationLink = $this->urlBuilder->getUrl(
                'admin_141y2y/customer/verify/',
                ['id' => $customer->getId()]
            );
            // Then in AdminEmailOnce method, before sending email:
            if (!$this->validateCustomerData($customerDataArray)) {
                $this->logger->error("❌ Cannot send email - missing required customer data");
                return;
            }
            // ✅ Send email
            $adminEmailSent = $this->sendEmail(
                $adminEmail,
                'Admin',
                '10',
                [
                    'data' => $customerDataObject,
                    'verification_link' => $verificationLink
                ]
            );
            
            // ✅ Create permanent lock file only if email was successfully sent
            if ($adminEmailSent) {
                if (file_put_contents($lockFile, time()) === false) {
                    $this->logger->error("❌ Failed to create lock file for customer ID: " . $customerId);
                } else {
                    $this->logger->info("✅ Email lock file created for customer ID: " . $customerId);
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error("❌ Error in AdminEmailOnce: " . $e->getMessage());
        } finally {
            // ✅ Always remove the temporary processing lock
            if (file_exists($tempLockFile)) {
                unlink($tempLockFile);
            }
        }
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
                ->setFrom(['email' => $this->shatchi_senderEmail, 'name' => $this->shatchi_senderName])
                ->addTo($toEmail, $toName)
                ->getTransport();

            $transport->sendMessage();
            $this->logger->info("✅ Email sent to: " . $toEmail);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("❌ Email error: " . $e->getMessage());
            return false;
        }
    }

    public function getCountryName($countryCode)
    {
        // ✅ FIX: Handle null/empty country codes properly
        if (!$countryCode || $countryCode === 'N/A1') {
            return 'N/A';
        }

        try {
            $country = $this->countryFactory->create()->loadByCode($countryCode);
            return $country->getName() ?: 'N/A';
        } catch (\Exception $e) {
            $this->logger->error("❌ Error getting country name for code '{$countryCode}': " . $e->getMessage());
            return 'N/A';
        }
    }

    public function getBusinessGroupName($groupId)
    {
        try {
            $group = $this->groupRepository->getById($groupId);
            return $group->getCode();
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}