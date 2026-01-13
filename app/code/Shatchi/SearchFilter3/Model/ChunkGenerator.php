<?php

namespace Shatchi\SearchFilter3\Model;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class ChunkGenerator
{
    const REDIS_KEY_PREFIX = 'searchfilter3:products:category:';
    const CACHE_LIFETIME = 86400; // 1 day
    // const CACHE_LIFETIME = 2592000; // 30 days


    protected $collectionFactory;
    protected $cache;
    protected $json;
    protected $categoryRepository;
    protected $storeManager;
    protected $logger;
    protected $layerResolver;
    protected $categoryCollectionFactory;

    public function __construct(
        ProductCollectionFactory $collectionFactory,
        CacheInterface $cache,
        Json $json,                          // ✅ Json comes third
        CategoryRepository $categoryRepository,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        LayerResolver $layerResolver,   // <-- ADD THIS
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->cache = $cache;
        $this->json = $json;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->layerResolver = $layerResolver; // <-- ASSIGN
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }


    /**
     * Fetch products and save JSON to Redis cache for a category
     */
    public function generateProductsCache($categoryId)
    {
        // $this->clearCategoryCache($categoryId);
        $cacheKey = self::REDIS_KEY_PREFIX . $categoryId;
        try {
            // $parentId = $categoryId; // your parent category ID
            $categoryIds = [$categoryId];

            $subcategories = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect('entity_id')
                ->addAttributeToFilter('is_active', 1)
                ->addAttributeToFilter('path', ['like' => "%/{$categoryId}/%"]);


            foreach ($subcategories as $subcategory) {
                $categoryIds[] = (int)$subcategory->getId();
            }

            $collection = $this->collectionFactory->create();
            // $collection->addAttributeToSelect(['name', 'price', 'image', 'small_image', 'thumbnail', 'url_key'])
            //     ->addAttributeToFilter('status', ['eq' => 1])
            //     ->addAttributeToFilter('visibility', ['neq' => 1])
            //     ->addCategoriesFilter(['in' => $categoryId]);
            // $layer = $this->layerResolver->get();
            // $category = $this->categoryRepository->get($categoryId);

            // $layer->setCurrentCategory($category); // Set current category in layer
            $this->logger->info(json_encode($categoryIds) . ' all sub category ID: ' . $categoryId);

            // $collection = $layer->getProductCollection();
            $collection->addAttributeToSelect(['name', 'price', 'image', 'small_image', 'thumbnail', 'url_key'])
                ->addAttributeToFilter('status', ['eq' => 1])
                ->addAttributeToFilter('visibility', ['neq' => 1])
                ->addCategoriesFilter(['in' => $categoryIds]);

            // (Optional but recommended) Join stock if you want 100% accurate in-stock info
            $collection->joinField(
                'is_in_stock',
                'cataloginventory_stock_item',
                'is_in_stock',

                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );
            $collection->joinField(
                'qty',
                'cataloginventory_stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );

            $collection->joinField(
                'min_sale_qty',
                'cataloginventory_stock_item',
                'min_sale_qty',

                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left'
            );
            $products = [];

            foreach ($collection as $product) {
                $typeId = $product->getTypeId();
                $price = 0.0;
                $children = []; // Default to empty to avoid undefined var later
                $hasVariants = false;

                if ($typeId === 'configurable') {
                    $children = $product->getTypeInstance()->getUsedProducts($product);
                    $prices = [];
                    foreach ($children as $child) {
                        $childPrice = (float) $child->getFinalPrice();
                        if ($childPrice > 0) {
                            $prices[] = $childPrice;
                        }
                    }
                    $price = !empty($prices) ? min($prices) : 0.0;
                } elseif ($typeId === 'grouped') {
                    // Important: ensure proper store context
                    $product->getTypeInstance()->setStoreFilter($this->storeManager->getStore(), $product);
                    $children = $product->getTypeInstance()->getAssociatedProducts($product);

                    $prices = [];
                    foreach ($children as $child) {
                        $childPrice = (float) $child->getFinalPrice();
                        if ($childPrice > 0) {
                            $prices[] = $childPrice;
                        }
                    }
                    $price = !empty($prices) ? min($prices) : 0.0;
                    $hasVariants=true;
                } else {
                    $price = (float) $product->getFinalPrice();
                }

                // Debug logs
                // if (in_array($typeId, ['grouped', 'configurable'])) {
                //     $this->logger->info('Grouped/Configurable product ID: ' . $product->getId());
                //     $this->logger->info('Children count: ' . count($children));
                //     $this->logger->info('Child IDs: ' . implode(', ', array_map(fn($c) => $c->getId(), $children)));
                // }
                // $this->logger->info('Price ' . $price . ' and type = ' . $typeId);

                $isInStock = (int) $product->getData('is_in_stock');
                $qty = (int) $product->getData('qty');
                $minQty = (int) $product->getData('min_sale_qty');
                $stockStatus = 'out_of_stock';
                // $hasInStockVariant = false;
                // foreach ($children as $child) {
                //     $childStock = (int) $child->getData('is_in_stock');
                //     $childQty = (int) $child->getData('qty');
                //     $childMinQty = (int) $child->getData('min_sale_qty');
                //     if ($childStock === 1 && $childQty >= $childMinQty && $childQty > 0) {
                //         $hasInStockVariant = true;
                //         break;
                //     }
                // }


                if ($typeId === 'grouped') {
                    // ✅ Grouped: if price is > 0 and qty available, show in stock
                    if (count($children) > 1  && $qty > 0) {
                        //Available in different Sizes                    
                         $hasVariants = true;
                    }
                } else {
                    // ✅ Normal logic for simple/configurable
                    if ($isInStock === 1 && $qty >= $minQty && $qty > 0) {
                        $stockStatus = 'in_stock';
                    }
                }

                // 💡 Skip grouped products if no child price was found
                if ($typeId === 'grouped' && $price <= 0) {
                    $this->logger->info('Skipping grouped product ID ' . $product->getId() . ' due to no valid price.');
                    continue; // Skip to next product
                }

                $products[] = [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => $price,
                    'image' => ($product->getImage() && $product->getImage() !== 'no_selection')
                        ?  $product->getImage()
                        : '/catalog/product/placeholder/default/no-image.jpg',
                    'stock' => $stockStatus,
                    'url_key' => $product->getProductUrl(),
                    'min_qty' => $minQty,
                    'has_variants' => $hasVariants , // ✅ new field
                    // 'has_in_stock_variant' => $hasInStockVariant,
                ];
            }

            $this->cache->save(
                $this->json->serialize($products),
                $cacheKey,
                [],
                self::CACHE_LIFETIME
            );

            // $this->logger->info('Generated product cache minQty ' . $minQty);
            // $cachedProducts = $this->cache->load('searchfilter3:products:category:' . $categoryId);
            // $products = $this->json->unserialize($cachedProducts);

            // // Correct logging without error
            // $this->logger->info('Generated product cache for category ID: ' . $categoryId, ['products' => $products]);


            return count($products); // Return total products saved
        } catch (\Exception $e) {
            $this->logger->error('Error generating cache for category ID ' . $categoryId . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clear Redis cache for a specific category
     */
    public function clearCategoryCache($categoryId)
    {
        $cacheKey = self::REDIS_KEY_PREFIX . $categoryId;

        try {
            $this->cache->remove($cacheKey);
            $this->logger->info('Cleared Redis cache for category ID: ' . $categoryId);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error clearing cache for category ID ' . $categoryId . ': ' . $e->getMessage());
            return false;
        }
    }
    public function getProductsByCategory($categoryId)
    {
        $cacheKey = self::REDIS_KEY_PREFIX . $categoryId;
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            return $this->json->unserialize($cached);
        }
        return [];
    }
}
