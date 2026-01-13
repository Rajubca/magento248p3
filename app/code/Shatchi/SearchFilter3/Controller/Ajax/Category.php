<?php
namespace Shatchi\SearchFilter3\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json;

class Category extends Action
{
    protected $cache;
    protected $jsonFactory;
    protected $json;

    public function __construct(
        Context $context,
        CacheInterface $cache,
        JsonFactory $jsonFactory,
        Json $json
    ) {
        parent::__construct($context);
        $this->cache = $cache;
        $this->jsonFactory = $jsonFactory;
        $this->json = $json;
    }

    public function execute()
    {
        $categoryId = (int) $this->getRequest()->getParam('category_id');
        $page = (int) $this->getRequest()->getParam('page', 1);

        $cacheKey = "searchfilter3_chunk_{$categoryId}_page_{$page}";
        $data = $this->cache->load($cacheKey);

        if ($data) {
            $result = $this->jsonFactory->create();
            return $result->setData($this->json->unserialize($data));
        }

        $result = $this->jsonFactory->create();
        return $result->setData(['html' => '<div>No more products.</div>', 'stop' => true]);
    }
}
