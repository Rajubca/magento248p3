<?php

namespace Shatchi\BannerSlider5\Controller\Config;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Psr\Log\LoggerInterface;

class SlideJsonSave extends Action
{
    protected $resultJsonFactory;
    protected $configResource;
    protected $configFactory;
    protected $logger;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \Shatchi\BannerSlider5\Model\ConfigFactory $configFactory,
        \Shatchi\BannerSlider5\Model\ResourceModel\Config $configResource,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configFactory = $configFactory;
        $this->configResource = $configResource;
        $this->logger = $logger;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $this->logger->debug('[SlideJsonSave] Ajax save triggered');

            $rawBody = $this->getRequest()->getContent();
            $this->logger->debug('[SlideJsonSave] Raw body:', ['body' => $rawBody]);

            $data = json_decode($rawBody, true);

            if (!isset($data['slide']) || !is_array($data['slide'])) {
                throw new \Exception('No slide data received.');
            }

            $slides = $data['slide'];
            $this->logger->debug('[SlideJsonSave] Parsed slides:', $slides);

            $model = $this->configFactory->create();
            $this->configResource->getConnection()->truncateTable($this->configResource->getMainTable());
            $model->setData(['slides_json' => json_encode($slides)]);
            $this->configResource->save($model);

            $this->logger->debug('[SlideJsonSave] Slides saved successfully.');

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('[SlideJsonSave] Error:', ['exception' => $e]);
            return $result->setData(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
