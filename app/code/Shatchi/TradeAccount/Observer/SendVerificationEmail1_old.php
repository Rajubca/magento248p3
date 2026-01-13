<?php

namespace Shatchi\TradeAccount\Observer;

use Magento\Framework\Stdlib\DateTime\DateTime;

// use Shatchi\TradeAccount\Model\ResourceModel\CustomerToken as CustomerTokenResource;
// use Shatchi\TradeAccount\Model\CustomerTokenFactory;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\DataObject; // ✅ Use Magento’s DataObject
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Directory\Model\CountryFactory;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\Registry;
use Magento\Customer\Model\Session;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\RequestInterface;

class SendVerificationEmail1 implements ObserverInterface
{
    protected $transportBuilder;
    protected $storeManager;
    protected $dateTime;
    // protected $customerTokenResource;
    // protected $customerTokenFactory;
    protected $scopeConfig;
    protected $customerRepository;
    protected $logger;
    protected $backendHelper;
    protected $resource;
    protected $urlBuilder;
    protected $countryFactory;
    protected $groupRepository;
    protected $formKey;
    protected static $emailSent = [];
    protected $customerRegistry;
    protected $customerResource;
    protected $encryptor;
    protected $customerSession;
    protected $registry;
    protected $request;
    protected $shatchi_senderName;
    protected $shatchi_senderEmail;


    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger,
        CountryFactory $countryFactory,
        GroupRepositoryInterface $groupRepository,
        ResourceConnection $resource,
        DateTime $dateTime,
        BackendHelper $backendHelper,
        // CustomerTokenResource $customerTokenResource,
        // CustomerTokenFactory $customerTokenFactory,
        FormKey $formKey,
        CustomerRegistry $customerRegistry,
        CustomerResource $customerResource,
        EncryptorInterface $encryptor,
        Session $customerSession,
        RequestInterface $request,
        Registry $registry

    ) {

        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        $this->countryFactory = $countryFactory;
        $this->groupRepository = $groupRepository;
        $this->resource = $resource;
        $this->dateTime = $dateTime;
        $this->backendHelper = $backendHelper;
        // $this->customerTokenResource = $customerTokenResource;
        // $this->customerTokenFactory = $customerTokenFactory;
        $this->formKey = $formKey;
        $this->customerRegistry = $customerRegistry;
        $this->customerResource = $customerResource;
        $this->encryptor = $encryptor;
        $this->customerSession = $customerSession;
        $this->request = $request; // ✅ Assign
        $this->registry = $registry;
    }

    public function execute(Observer $observer)
    {
        $customer = $observer->getEvent()->getCustomer();
        // $customer_1 = $observer->getCustomer();
        $this->shatchi_senderName = $this->scopeConfig->getValue(
            'shatchi_tradeaccount_email/shatchi_sender_settings/shatchi_sender_name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $this->shatchi_senderEmail = $this->scopeConfig->getValue(
            'shatchi_tradeaccount_email/shatchi_sender_settings/shatchi_sender_email',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (!$customer) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Customer data is missing.'));
        }

        $customerId = $customer->getId();
        $customerEmail = $customer->getEmail();
        $shippingAddress = $customer->getDefaultShippingAddress();


        // Check if email is already sent
        if (isset(self::$emailSent[$customerId])) {
            return;
        }



        // Check if this save is happening for the customer grid
        if (!$this->isGridUpdate($customerId)) {
            return;
        }
        // ✅ Get approval_status from `customer_grid_flat`
        $connection = $this->resource->getConnection();
        $gridTable = $this->resource->getTableName('customer_grid_flat');

        // Fetch old approval_status from grid table
        $select = $connection->select()
            ->from($gridTable, ['is_approved'])
            ->where('entity_id = ?', $customerId);

        $oldStatus = $connection->fetchOne($select);

        $newStatus = $customer->getData('is_approved');

        $this->logger->info("✅ Old =" . $oldStatus . " and  new status =" . $newStatus . " new condition=" . ((empty($oldStatus) && empty($newStatus)) || $oldStatus === "new"));

        
        $connection = $this->resource->getConnection();
        $customerTable = $this->resource->getTableName('customer_entity');
        $customerTextTable = $this->resource->getTableName('customer_entity_text');
        $customerIntTable = $this->resource->getTableName('customer_entity_int');
        $businessGroupTable = $this->resource->getTableName('customer_group'); // Change this if your business group table is different

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



        if ((empty($oldStatus) && empty($newStatus)) || ($oldStatus === "new" && $newStatus === "new")) {
            $connection->update(
                $gridTable,
                ['is_approved' => 'pending'],
                ['entity_id = ?' => $customerId]
            );

            // $this->logger->info("✅ Status changed to 'pending' successfully.");
            // if (!$this->checkCustomerRegisteredOrNot($customer)) {
            $adminEmailSent = $this->sendEmail(
                $customer->getEmail(),
                'Admin',
                '10', // ✅ Correct identifier
                [
                    'data' => [], // ✅ Now passes as an object
                    'verification_link' => ""
                ]
            );
            // }
            // Refresh the customer object
            $customer->setData('is_approved', 'pending');
            $this->customerResource->save($customer);

            try {
                // Log email sending attempt
                $this->logger->info("✅ Sending verification email to: " . $customerEmail);
                $approvalUrl = $this->urlBuilder->getUrl(
                    'admin_141y2y',
                    ['_secure' => true]
                );
                $rejectionUrl = $this->urlBuilder->getUrl(
                    'admin_141y2y/account/redirectverify',
                    ['id' => $customerId, 'action' => 'reject', '_secure' => true]
                );
                $totalOutlets = $this->request->getParam('total_outlets');
                $customerWebsite = $this->request->getParam('customer_website');
                $customersMessage = $this->request->getParam('customers_message');

                $this->logger->info('Total Outlets: ' . $totalOutlets);
                $this->logger->info('Customer Website: ' . $customerWebsite);
                $this->logger->info('Customer Message: ' . $customersMessage);

                $formKey = $this->formKey->getFormKey();
                $businessAddress = $shippingAddress ? $shippingAddress->getStreet() : ['N']; // Get street address array
                $customerDataArray = [
                    'customer_id' => $customerId,
                    'customer_name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                    'customer_email' => $customer->getEmail(),
                    'business_name' => $shippingAddress ? $shippingAddress->getCompany() : 'N/A',
                    'business_phone' => $shippingAddress ? $shippingAddress->getTelephone() : 'N/A',
                    'business_address' => $shippingAddress ? implode(', ', $businessAddress) : 'N/A',
                    'business_city' => $shippingAddress ? $shippingAddress->getCity() : 'N/A',
                    'business_postcode' => $shippingAddress ? $shippingAddress->getPostcode() : 'N/A',
                    'business_country' => $this->getCountryName($shippingAddress ? $shippingAddress->getCountryId() : 'N/A'),
                    'business_type' => $businessGroupName, // Fetch Business Group Nam
                    'total_outlets' => $totalOutlets,
                    'customer_website' => $customerWebsite,
                    'customer_message' => $customersMessage,
                    'approve_link' => $approvalUrl,
                    'reject_link' => $rejectionUrl,
                    // 'adminCustomerUrl' => $adminCustomerUrl,
                ];

                $this->logger->info("Approval URL: " . $approvalUrl);
                $this->logger->info("Rejection URL: " . $rejectionUrl);

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


                $adminEmailSent = $this->sendEmail(
                    $adminEmail,
                    'Admin',
                    '10', // ✅ Correct identifier
                    [
                        'data' => $customerDataObject, // ✅ Now passes as an object
                        'verification_link' => $verificationLink
                    ]
                );
                // // Example: $this->sendEmail($customerEmail); STops welcome mail from our side
                // if ($adminEmailSent) {
                //     // 2️⃣ Send Welcome Email to Customer Only If Admin Mail Sent
                //     $this->sendEmail(
                //         $customer->getEmail(),
                //         $customer->getFirstname(),
                //         '9', // ✅ Correct identifier
                //         [] // ✅ Now an object instead of raw array
                //     );
                // } else {
                //     $this->logger->error("❌ Admin verification email failed, so the customer welcome email was NOT sent.");
                // }
                // Mark email as sent
                // Update the is_approved status to 'pending'

                self::$emailSent[$customerId] = true;
            } catch (\Exception $e) {
                $this->logger->error("❌ Error in email sending: " . $e->getMessage());
            }
        }
        if ($oldStatus !== $newStatus) {
            if ($newStatus === "approved") {
                $this->logger->info("Approval status changed to Approved. Sending approval email and password reset email.");

                // 1️⃣ Send the approval email
                // $this->sendEmail(
                //     $customerEmail,
                //     $customer->getFirstname(),
                //     '11', // Approval email template ID
                //     [
                //         'customer_name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                //         'status' => 'Approved'
                //     ]
                // );




                $store = $this->storeManager->getStore(); // Get current store
                $baseUrl = $store->getBaseUrl(); // Get frontend base URL
                // Get password reset link
                $resetUrl = $baseUrl . 'customer/account/forgotpassword/?email=' . $customer->getEmail();
                $login_new = $baseUrl . 'customer/account/login/?email=' . $customer->getEmail();
                $name = $customer->getFirstname() . ' ' . $customer->getLastname();
                // Send email
                $templateVars = [
                    'customer_name' => $name,

                    'login_new' => $login_new,
                    'password_reset_link' => $resetUrl,

                ];

                // 3️⃣ Send Reset Password Email
                $this->sendEmail(
                    $customerEmail,
                    $customer->getFirstname(),
                    '11', // Password Reset email template ID 14 to 11
                    $templateVars,
                    // [
                    //     'customer_name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                    //     'reset_password_link' => $resetPasswordUrl
                    // ]
                );

                $this->logger->info("✅ Reset Password email sent to: " . $customerEmail);
                // } catch (\Exception $e) {
                //     $this->logger->error("❌ Error in sending reset password email: " . $e->getMessage());
                // }
            }
            if ($newStatus === "notapproved") {
                $this->logger->info("Approval status changed to Rejected. Sending rejection email.");

                $this->sendEmail(
                    $customerEmail,
                    $customer->getFirstname(),
                    '12', // Rejection email template ID
                    [
                        'customer_name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                        'status' => 'Rejected'
                    ]
                );
            }
            if($newStatus === "pending" && $oldStatus==="pending"){
                $this->logger->info("Customer {$customerEmail} is registered, verification is pending. Sending pending email.");

                $this->sendEmail(
                    $customerEmail,
                    trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                    '17', // <-- your verification pending template ID
                    [
                        'customer_name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
                        'customer_email' => $customerEmail,
                    ]
                );
                self::$emailSent[$customerId] = true;
            }
        }
    }

    public function getAdminCustomerEditUrl($customerId)
    {
        return $this->backendHelper->getUrl('customer/index/edit', ['id' => $customerId]);
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

    private function isGridUpdate($customerId)
    {
        $connection = $this->resource->getConnection();
        $gridTable = $this->resource->getTableName('customer_grid_flat');

        $select = $connection->select()
            ->from($gridTable, 'entity_id')
            ->where('entity_id = ?', $customerId);

        return (bool) $connection->fetchOne($select);
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
