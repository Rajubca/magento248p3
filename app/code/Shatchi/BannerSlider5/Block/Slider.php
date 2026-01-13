<?php

namespace Shatchi\BannerSlider5\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Shatchi\BannerSlider5\Model\ResourceModel\Config\CollectionFactory;

class Slider extends Template
{
    private const CONFIG_PATH_ENABLED = 'shatchi_bannerslider5/general/enabled';
    private const CONFIG_PATH_AUTOPLAY = 'shatchi_bannerslider5/general/autoplay';
    private const CONFIG_PATH_AUTOPLAY_SPEED = 'shatchi_bannerslider5/general/autoplay_speed';
    private const CONFIG_PATH_DEFAULT_OFFER = 'shatchi_bannerslider5/general/default_offer';
    private const CONFIG_PATH_DEFAULT_ANIMATION = 'shatchi_bannerslider5/general/default_animation';

    /**
     * @var ScopeConfigInterface
     */
    protected $configCollectionFactory;
    
    // Note: $scopeConfig is already available in parent $context, but injecting it is fine.
    protected $scopeConfig;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $configCollectionFactory,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configCollectionFactory = $configCollectionFactory;
        // It is good practice to set a default template here so CMS calls don't strictly require it
        if (!isset($data['template'])) {
            $data['template'] = 'Shatchi_BannerSlider5::slider.phtml';
        }
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getSlides(): array
    {
        try {
            // Add safety check if collection is empty
            $collection = $this->configCollectionFactory->create();
            $item = $collection->getFirstItem();
            
            if (!$item->getId()) {
                return [];
            }

            $json = $item->getData('slides_json');

            if (empty($json)) {
                return [];
            }

            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : [];
            
        } catch (\Exception $e) {
            // Log error to prevent frontend crash
            $this->_logger->error('BannerSlider5 Error: ' . $e->getMessage());
            return [];
        }
    }

    public function getAutoplay(): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_AUTOPLAY,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getAutoplaySpeed(): int
    {
        $value = $this->scopeConfig->getValue(
            self::CONFIG_PATH_AUTOPLAY_SPEED,
            ScopeInterface::SCOPE_STORE
        );
        
        // Fix: Ensure we check value before casting
        return (int)($value ?: 3000);
    }

    public function getOfferText(): ?string
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_DEFAULT_OFFER,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getDefaultAnimation(): string
    {
        $value = $this->scopeConfig->getValue(
            self::CONFIG_PATH_DEFAULT_ANIMATION,
            ScopeInterface::SCOPE_STORE
        );
        
        return $value ?: 'fade';
    }
}