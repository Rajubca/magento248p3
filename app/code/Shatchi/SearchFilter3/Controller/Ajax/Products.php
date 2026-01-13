<?php
namespace Shatchi\SearchFilter3\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Customer\Model\Context as CustomerContext;

class Products extends Action
{
    protected $resultJsonFactory;
    protected $cache;
    protected $json;
    protected $customerSession;
    protected $httpContext;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CacheInterface $cache,
        Json $json,
        CustomerSession $customerSession,
        HttpContext $httpContext
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cache             = $cache;
        $this->json              = $json;
        $this->customerSession   = $customerSession;
        $this->httpContext       = $httpContext;
    }

    public function execute()
    {
        $categoryId = (int)$this->getRequest()->getParam('category_id');
        if ($categoryId <= 0) {
            return $this->resultJsonFactory->create()->setData([
                'success' => false,
                'message' => 'Missing or invalid category_id.'
            ]);
        }

        // Robust auth check (covers FPC/varnish contexts as well)
        $isLoggedIn = $this->customerSession->isLoggedIn()
            || (bool)$this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);

        $cacheKey = 'searchfilter3:products:category:' . $categoryId;
        $cachedProducts = $this->cache->load($cacheKey);

        $result = $this->resultJsonFactory->create();

        // Always set conservative cache headers (avoid leaking private data)
        $this->getResponse()->setHeader(
            'Cache-Control',
            $isLoggedIn
                ? 'private, no-store, no-cache, must-revalidate, max-age=0'
                : 'public, no-store, no-cache, must-revalidate, max-age=0',
            true
        );
        $this->getResponse()->setHeader('Pragma', 'no-cache', true);

        if (!$cachedProducts) {
            return $result->setData([
                'success' => false,
                'message' => 'No products found for this category.'
            ]);
        }

        $products = $this->json->unserialize($cachedProducts);

        // If guest, strip sensitive fields from every product
        if (!$isLoggedIn) {
            foreach ($products as &$p) {
                $this->stripSensitive($p);
            }
            unset($p);
        }

        return $result->setData([
            'success'    => true,
            'logged_in'  => $isLoggedIn,
            'products'   => $products
        ]);
    }

    /**
     * Remove price/stock fields for guests (handles different keys safely).
     * Adjust the list to match your actual JSON structure.
     */
    private function stripSensitive(array &$item): void
    {
        $keysToUnset = [
            'price', 'final_price', 'special_price', 'regular_price',
            'price_incl_tax', 'price_excl_tax', 'formatted_price',
            'tier_prices', 'minimal_price', 'max_price', 'min_price',
            'qty', 'quantity', 'stock', 'stock_qty', 'stock_status',
            'is_in_stock', 'salable_qty'
        ];

        foreach ($keysToUnset as $k) {
            if (array_key_exists($k, $item)) {
                unset($item[$k]);
            }
        }

        // If nested price objects/arrays exist, nuke them safely
        foreach (['prices', 'stock_data', 'inventory'] as $nested) {
            if (isset($item[$nested])) {
                unset($item[$nested]);
            }
        }
    }
}
