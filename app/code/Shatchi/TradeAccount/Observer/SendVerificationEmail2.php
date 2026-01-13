<?php

namespace Shatchi\TradeAccount\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;


class SendVerificationEmail2 implements ObserverInterface
{
    protected $logger;
    protected $resource;
    protected $registry;
    protected $storeManager;
    protected $transportBuilder;
    protected $scopeConfig;
    protected $shatchi_senderName ;
    protected $shatchi_senderEmail ;

    public function __construct(
        LoggerInterface $logger,
        ResourceConnection $resource,
        Registry $registry,
        StoreManagerInterface $storeManager,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->logger = $logger;
        $this->resource = $resource;
        $this->registry = $registry;
        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;

    }

    public function execute(Observer $observer)
    {
        $customer = $observer->getCustomer();
        $customerEmail = $customer->getEmail();
        $customerName=trim($customer->getFirstname() . ' ' . $customer->getLastname());
        $store = $this->storeManager->getStore();
        $baseUrl = $store->getBaseUrl();
        $loginUrl = $baseUrl . 'customer/account/login/';
        $this->shatchi_senderName = $this->scopeConfig->getValue('shatchi_tradeaccount_email/shatchi_sender_settings/shatchi_sender_name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $this->shatchi_senderEmail = $this->scopeConfig->getValue('shatchi_tradeaccount_email/shatchi_sender_settings/shatchi_sender_email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        
        if ($this->isPasswordChanged($customer)) {
            $this->logger->info("✅ Customer Save Before: Password Changed");
            $this->sendEmail(
                $customerEmail,
                $customerName,
                '15', // Use correct email template ID
                [
                    'customer_name' => $customerName,
                    'login_url' => $loginUrl,
                ]
            );
        }
        // else {
        //     // $this->logger->info("✅ Customer Save Before: Password Not Changed");
        // }

        
    }

    private function isPasswordChanged($customer)
    {
        $customerId = $customer->getId();
        $connection = $this->resource->getConnection();
        $customerTable = $this->resource->getTableName('customer_entity');

        // Get the last known password hash
        $select = $connection->select()
            ->from($customerTable, ['password_hash'])
            ->where('entity_id = ?', $customerId);

        $oldPassword = $connection->fetchOne($select);

        if (!$oldPassword) {
            return false; // No password set, avoid false triggers
        }

        // Retrieve cached password hash from the registry
        $newPassword = $customer->getData('password_hash');
        $this->logger->info("Password Change Check Called.");

        if ($newPassword && $newPassword !== $oldPassword) {
            $this->logger->info("✅ Password changed. Prev: " . $newPassword . " | Curr: " . $oldPassword);
            return true;
        }

        $this->logger->info("❌ Password not changed. Prev: " . $newPassword . " | Curr: " . $oldPassword);
        return false;
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
}
