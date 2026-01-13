<?php
namespace Shatchi\SpamTracker\Plugin;

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\InputException;
use Shatchi\SpamTracker\Model\SpamLogFactory;
use Shatchi\SpamTracker\Model\ResourceModel\SpamLog as SpamLogResource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class AccountManagementPlugin
{
    private $spamLogFactory;
    private $spamLogResource;
    private $scopeConfig;
    private $logger;

    public function __construct(
        SpamLogFactory $spamLogFactory,
        SpamLogResource $spamLogResource,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->spamLogFactory = $spamLogFactory;
        $this->spamLogResource = $spamLogResource;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function beforeCreateAccount(
        AccountManagementInterface $subject,
        CustomerInterface $customer,
        $password = null,
        $redirectUrl = ''
    ) {
        $email = filter_var($customer->getEmail(), FILTER_SANITIZE_EMAIL);
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InputException(__('Invalid email format.'));
        }

        // Get spam patterns from configuration
        $patterns = $this->scopeConfig->getValue(
            'spamtracker/general/spam_email_patterns',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        // Log patterns for debugging
        $this->logger->debug('SpamTracker: Patterns loaded', ['patterns' => $patterns, 'email' => $email]);

        // If no patterns configured, allow registration
        if (empty($patterns)) {
            $this->logger->debug('SpamTracker: No patterns configured, allowing registration');
            return [$customer, $password, $redirectUrl];
        }

        // Split patterns into array and check if email contains any
        $patternArray = array_map('trim', explode(',', $patterns));
        foreach ($patternArray as $pattern) {
            if (!empty($pattern) && stripos($email, $pattern) !== false) {
                // Log the attempt
                $spamLog = $this->spamLogFactory->create();
                $spamLog->setEmail($email);
                $this->spamLogResource->save($spamLog);

                // Log match for debugging
                $this->logger->debug('SpamTracker: Spam detected', ['email' => $email, 'matched_pattern' => $pattern]);

                // Prevent registration
                throw new InputException(__('Spam detected. Registration blocked.'));
            }
        }

        // Log non-spam for debugging
        $this->logger->debug('SpamTracker: No spam detected, allowing registration', ['email' => $email]);

        return [$customer, $password, $redirectUrl];
    }
}