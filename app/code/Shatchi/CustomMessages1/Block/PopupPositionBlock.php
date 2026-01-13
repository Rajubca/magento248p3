<?php
namespace Shatchi\CustomMessages1\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class PopupPositionBlock extends Template
{
    protected $scopeConfig;
    protected $logger;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger, // Inject the logger
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    public function getPopupPosition()
    {
        $position = $this->scopeConfig->getValue(
            'shatchi_settings/search_filter3/general_options/popup_position',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        // $position = $this->scopeConfig->getValue(
        //     'shatchi_custommessages1/general/popup_position',
        //     \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        // );

        // Log the position value
        $this->logger->debug('Popup Position Config Value: ' . $position);

        return $position;
    }
}
