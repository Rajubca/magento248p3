<?php
namespace Shatchi\SearchFilter3\Controller\Ajax;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Shatchi\SearchFilter3\Model\ChunkGenerator;
use Magento\Framework\App\CacheInterface;

class Chunk extends Action
{
    protected $resultJsonFactory;
    protected $chunkGenerator;
    protected $redis;


    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ChunkGenerator $chunkGenerator,
        CacheInterface $redis
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->chunkGenerator = $chunkGenerator;
         $this->redis = $redis;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $categoryId = (int) $this->getRequest()->getParam('category_id');

        if (!$categoryId) {
            return $result->setData([
                'success' => false,
                'message' => 'Category ID is required.'
            ]);
        }

        try {
            $products = $this->chunkGenerator->getProductsByCategory($categoryId);

            return $result->setData([
                'success' => true,
                'products' => $products
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
}
