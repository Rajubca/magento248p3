<?php
namespace Shatchi\SearchFilter3\Block;

use Magento\Catalog\Block\Product\ListProduct as MagentoListProduct;
use Magento\Framework\App\CacheInterface;
use Magento\Catalog\Block\Product\Context;

class ListProduct extends MagentoListProduct
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @param Context $context
     * @param CacheInterface $cache
     * @param array $data
     */
    public function __construct(
        Context $context,
        CacheInterface $cache,
        array $data = []
    ) {
        // 1. Assign your custom dependency
        $this->cache = $cache;

        // 2. Pass the minimum required context and data to parent
        // Magento's DI will handle the rest of the parent dependencies automatically
        parent::__construct(
            $context,
            ...array_values($data) 
        );
        
        // Note: If the above parent call fails, use the explicit version below
    }

    /**
     * Manual override for Magento 2.4.8-p3 strict signature
     */
    public function getCustomCacheData($key)
    {
        return $this->cache->load($key);
    }

    public function saveCustomCacheData($key, $data)
    {
        $this->cache->save($data, $key, [], 3600);
    }
}