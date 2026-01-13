<?php
namespace Shatchi\TradeAccount\Plugin\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Controller\Adminhtml\Index\InlineEdit as Subject;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Magento\Customer\Api\Data\CustomerExtensionFactory;

class InlineEditPlugin
{
    private $customerRepository;
    private $logger;
    private $jsonFactory;
    private $customerExtensionFactory;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger,
        JsonFactory $jsonFactory,
        CustomerExtensionFactory $customerExtensionFactory
    ) {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        $this->jsonFactory = $jsonFactory;
        $this->customerExtensionFactory = $customerExtensionFactory;
    }

    public function aroundExecute(Subject $subject, callable $proceed)
    {
        $resultJson = $this->jsonFactory->create();
        $items = $subject->getRequest()->getParam('items', []);

        if (!($subject->getRequest()->getParam('isAjax') && count($items))) {
            return $resultJson->setData([
                'messages' => [__('Please correct the data sent.')],
                'error' => true,
            ]);
        }

        $this->logger->debug('Inline Edit Request Data: ' . json_encode($items));

        $messages = [];
        $errors = false;

        foreach ($items as $customerId => $itemData) {
            try {
                $customer = $this->customerRepository->getById($customerId);

                // Log before changes
                $extensionAttributesBefore = $customer->getExtensionAttributes();
                $logDataBefore = [
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                    'group_id' => $customer->getGroupId(),
                    'taxvat' => $customer->getTaxvat(),
                    'gender' => $customer->getGender(),
                    'total_outlets' => $extensionAttributesBefore ? $extensionAttributesBefore->getTotalOutlets() : null,
                    'customers_message' => $extensionAttributesBefore ? $extensionAttributesBefore->getCustomersMessage() : null,
                    'customer_website' => $extensionAttributesBefore ? $extensionAttributesBefore->getCustomerWebsite() : null,
                    'is_approved' => $customer->getCustomAttribute('is_approved') ? $customer->getCustomAttribute('is_approved')->getValue() : null,
                ];
                $this->logger->debug('Customer Data Before Save: ' . json_encode($logDataBefore));

                // Initialize extension attributes if not set
                $extensionAttributes = $customer->getExtensionAttributes();
                if (!$extensionAttributes) {
                    $extensionAttributes = $this->customerExtensionFactory->create();
                }

                // Process all specified fields from itemData
                foreach ($itemData as $key => $value) {
                    switch ($key) {
                        // Standard fields
                        case 'email':
                            $this->logger->debug("Setting email: $value");
                            $customer->setEmail($value);
                            break;
                        case 'group':
                        case 'group_id':
                            $this->logger->debug("Setting group_id: $value");
                            $customer->setGroupId($value);
                            break;
                        case 'tax':
                        case 'taxvat':
                            $this->logger->debug("Setting taxvat: $value");
                            $customer->setTaxvat($value);
                            break;
                        case 'gender':
                            $this->logger->debug("Setting gender: $value");
                            $customer->setGender($value);
                            break;
                        // Custom extension attributes
                        case 'total_outlets':
                            $this->logger->debug("Setting total_outlets: $value");
                            $extensionAttributes->setTotalOutlets($value);
                            break;
                        case 'customers_message':
                            $this->logger->debug("Setting customers_message: $value");
                            $extensionAttributes->setCustomersMessage($value);
                            break;
                        case 'customer_website':
                            $this->logger->debug("Setting customer_website: $value");
                            $extensionAttributes->setCustomerWebsite($value);
                            break;
                        // Vendor's is_approved (EAV attribute)
                        case 'is_approved':
                            $this->logger->debug("Setting is_approved: $value");
                            $customer->setCustomAttribute('is_approved', $value);
                            break;
                        default:
                            $this->logger->debug("Skipping unknown field: $key");
                            break;
                    }
                }

                $customer->setExtensionAttributes($extensionAttributes);

                $this->logger->debug('Attempting to save customer ID: ' . $customerId);
                $this->customerRepository->save($customer);
                $this->logger->debug('Customer save completed for ID: ' . $customerId);

                // Log after save
                $extensionAttributesAfter = $customer->getExtensionAttributes();
                $logDataAfter = [
                    'id' => $customer->getId(),
                    'email' => $customer->getEmail(),
                    'group_id' => $customer->getGroupId(),
                    'taxvat' => $customer->getTaxvat(),
                    'gender' => $customer->getGender(),
                    'total_outlets' => $extensionAttributesAfter ? $extensionAttributesAfter->getTotalOutlets() : null,
                    'customers_message' => $extensionAttributesAfter ? $extensionAttributesAfter->getCustomersMessage() : null,
                    'customer_website' => $extensionAttributesAfter ? $extensionAttributesAfter->getCustomerWebsite() : null,
                    'is_approved' => $customer->getCustomAttribute('is_approved') ? $customer->getCustomAttribute('is_approved')->getValue() : null,
                ];
                $this->logger->debug('Customer Data After Save: ' . json_encode($logDataAfter));
            } catch (LocalizedException $e) {
                $messages[] = $this->getErrorWithCustomerId($customerId, $e->getMessage());
                $errors = true;
                $this->logger->debug('LocalizedException: ' . $e->getMessage());
            } catch (\Exception $e) {
                $messages[] = $this->getErrorWithCustomerId($customerId, __('Something went wrong: %1', $e->getMessage()));
                $errors = true;
                $this->logger->debug('Exception: ' . $e->getMessage());
            }
        }

        return $resultJson->setData([
            'messages' => $messages,
            'error' => $errors
        ]);
    }

    private function getErrorWithCustomerId($customerId, $error)
    {
        return __('Customer ID %1: %2', $customerId, $error);
    }
}