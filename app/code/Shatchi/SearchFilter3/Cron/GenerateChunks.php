<?php
namespace Shatchi\SearchFilter3\Cron;

use Shatchi\SearchFilter3\Model\ChunkGenerator;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Psr\Log\LoggerInterface;

class GenerateChunks
{
    protected $chunkGenerator;
    protected $categoryCollectionFactory;
    protected $logger;

    public function __construct(
        ChunkGenerator $chunkGenerator,
        CategoryCollectionFactory $categoryCollectionFactory,
        LoggerInterface $logger
    ) {
        $this->chunkGenerator = $chunkGenerator;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->logger = $logger;
    }

    public function execute()
    {
        try {
            $this->logger->info('Starting category chunks generation');
            
            // Get all active categories (level 2 and above)
            $collection = $this->categoryCollectionFactory->create()
                ->addAttributeToSelect(['entity_id', 'name'])
                ->addAttributeToFilter('is_active', 1)
                ->addAttributeToFilter('level', ['gteq' => 2]);
            
            $totalCategories = $collection->getSize();
            $this->logger->info("Found {$totalCategories} active categories to process");
            
            $processed = 0;
            $batchSize = 100;
            $collection->setPageSize($batchSize);
            
            $pages = $collection->getLastPageNumber();
            for ($page = 1; $page <= $pages; $page++) {
                $collection->setCurPage($page);
                $collection->load();
                
                foreach ($collection as $category) {
                    try {
                        $this->logger->info("Processing category ID: {$category->getId()} - {$category->getName()}");
                        $this->chunkGenerator->generateProductsCache((int)$category->getId());
                        $processed++;
                    } catch (\Exception $e) {
                        $this->logger->error(
                            "Error processing category ID: {$category->getId()} - " . $e->getMessage()
                        );
                    }
                }
                
                // Clear collection and free memory
                $collection->clear();
            }
            
            $this->logger->info("Completed chunks generation. Processed {$processed}/{$totalCategories} categories");
        } catch (\Exception $e) {
            $this->logger->critical("Error in GenerateChunks cron: " . $e->getMessage());
        }
        
        return $this;
    }
}