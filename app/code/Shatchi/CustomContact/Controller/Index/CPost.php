<?php

namespace Shatchi\CustomContact\Controller\Index;

// <?php
use Shatchi\CustomContact\Model\ContactSupportFactory;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\DataObject;

class CPost extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{
	protected $dataPersistor;
	protected $uploaderFactory;
	protected $filesystem;
	protected $logger;
	protected $dateTime;
	protected $transportBuilder;
	protected $_storeManager;
	protected $scopeConfig;
	protected $contactSupportFactory;

	public function __construct(
		Context $context,
		DataPersistorInterface $dataPersistor,
		UploaderFactory $uploaderFactory,
		Filesystem $filesystem,
		LoggerInterface $logger,
		DateTime $dateTime,
		TransportBuilder $transportBuilder,
		StoreManagerInterface $storeManager,
		ScopeConfigInterface $scopeConfig,
		ContactSupportFactory $contactSupportFactory // ✅ Inject factory 
	) {
		$this->dataPersistor = $dataPersistor;
		$this->uploaderFactory = $uploaderFactory;
		$this->filesystem = $filesystem;
		$this->logger = $logger;
		$this->dateTime = $dateTime;
		$this->transportBuilder = $transportBuilder;
		$this->_storeManager = $storeManager;
		$this->scopeConfig = $scopeConfig;
		$this->contactSupportFactory = $contactSupportFactory; // ✅ Assign it

		parent::__construct($context);
	}

	public function execute()
	{
		$data = $this->getRequest()->getPostValue();

		if (!$data) {
			$this->messageManager->addErrorMessage(__('Invalid form submission.'));
			return $this->_redirect('*/*/');
		}

		try {
			// Handle file upload
			$uploadedFiles = $this->processFileUploads(['invoice_upload', 'product_upload']);

			// Add uploaded file paths to data
			if (!empty($uploadedFiles)) {
				$data = array_merge($data, $uploadedFiles);
			}


			// Send email
			$this->sendEmail($data);

			$this->messageManager->addSuccessMessage(__('If you got email then inquiry was submitted successfully. Thanks for contacting us with your comments and questions. We\'ll respond to you very soon.'));
		} catch (LocalizedException $e) {
			$this->messageManager->addErrorMessage($e->getMessage());
		} catch (\Exception $e) {

			$this->messageManager->addErrorMessage(__('Something went wrong while submitting the form.'));
		}

		return $this->_redirect('*/*/');
	}
	private function sendEmail($post)
	{
		try {
			$templateId = (isset($post['customer_type']) && $post['customer_type'] === 'consumer') ? 7 : 8;

			$primaryEmail = $this->scopeConfig->getValue(
				'shatchi_contactsupport/email_settings/primary_email',
				\Magento\Store\Model\ScopeInterface::SCOPE_STORE
			);

			// $primaryEmail = 'sales@shatchi.co.uk';
			// $primaryEmail = 'ecommerce@shatchi.co.uk';
			// $primaryEmail = 'rajubca013r@hotmail.com';

			// Log before sending to admin
			$this->logger->info("Sending email to PRIMARY: $primaryEmail with template ID: $templateId");

			$this->sendWithTransport($primaryEmail, $post, $templateId);

			if ($templateId === 7) {
				// $secondaryEmail = 'productsupport@shatchi.co.uk';
				// $secondaryEmail = 'rajubca013r@hotmail.com';
				$secondaryEmail = $this->scopeConfig->getValue(
					'shatchi_contactsupport/email_settings/secondary_email',
					\Magento\Store\Model\ScopeInterface::SCOPE_STORE
				);

				$this->logger->info("Sending email to SECONDARY: $secondaryEmail with template ID: $templateId");

				$this->sendWithTransport($secondaryEmail, $post, $templateId);
			}
			//Saving data to database also
			$contact = $this->contactSupportFactory->create();
			$data = $post;
			$contact->setData([
				'name' => $data['name'] ?? '',
				'email' => $data['email'] ?? '',
				'telephone' => $data['telephone'] ?? '',
				'comment' => $data['comment'] ?? '',
				'customer_type' => $data['customer_type'] ?? '',
				'invoice_upload' => $data['invoice_upload'] ?? '',
				'product_upload' => $data['product_upload'] ?? '',
			]);

			$contact->save();


			// 🆕 Email to customer
			if (!empty($post['email'])) {
				$this->logger->info("Sending email to CUSTOMER: {$post['email']} with template ID: 16");
				$this->sendWithTransport($post['email'], $post, 16);
				$this->logger->info("Sending email Successful to CUSTOMER: {$post['email']} with template ID: 16");
			} else {
				$this->logger->warning("Customer email not found in post data.");
			}
		} catch (\Exception $e) {
			$this->logger->error("Email sending failed: " . $e->getMessage());
			throw new \Exception(__('Unable to send email. Please try again later.'));
		}
	}


	private function sendWithTransport($recipientEmail, $data, $templateId)
	{
		try {
			$emailReplyTo = $data['email'] ?? null; // Get customer's email
			$transport = $this->transportBuilder
				->setTemplateIdentifier($templateId) // Use the specific template ID
				->setTemplateOptions([
					'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
					'store' => $this->_storeManager->getStore()->getId(),
				])
				->setTemplateVars(['data' => new \Magento\Framework\DataObject($data)]) // Pass data to the template
				->setFromByScope('general') // Sender configuration
				->addTo($recipientEmail) // Recipient email
			;
			// ✅ Add Reply-To only if customer's email is valid
			if ($emailReplyTo && filter_var($emailReplyTo, FILTER_VALIDATE_EMAIL)) {
				$transport->setReplyTo($emailReplyTo);
			}
			$transport->getTransport()->sendMessage();
		} catch (\Exception $e) {

			throw $e;
		}
	}



	private function processFileUploads(array $fields)
	{
		$uploadedFiles = [];
		$mediaPath = 'custom_upload/';
		$mediaAbsolutePath = $this->filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA)
			->getAbsolutePath($mediaPath);

		foreach ($fields as $field) {
			try {
				if (isset($_FILES[$field]) && $_FILES[$field]['name']) {
					// Check file size (5 MB = 5 * 1024 * 1024 bytes)
					if ($_FILES[$field]['size'] > 2097152) { // 2 MB in bytes
						throw new LocalizedException(__('The uploaded file exceeds the maximum allowed size'));
					}

					$uploader = $this->uploaderFactory->create(['fileId' => $field]);
					$uploader->setAllowedExtensions(['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);
					$uploader->setAllowRenameFiles(true);
					$uploader->setFilesDispersion(true);

					$result = $uploader->save($mediaAbsolutePath);

					if ($result) {
						$uploadedFiles[$field] = $result['file'];
					}
				}
			} catch (LocalizedException $e) {
				throw $e; // Re-throw file size exceptions with the specific message
			} catch (\Exception $e) {

				throw new LocalizedException(__('Unable to upload file: %1', $field));
			}
		}

		return $uploadedFiles;
	}
}
