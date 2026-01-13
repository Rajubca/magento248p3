<?php

namespace Shatchi\SearchFilter3\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Shatchi\SearchFilter3\Model\ChunkGenerator;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

class GenerateChunksCommand extends Command
{
    const CATEGORY_OPTION = 'category';
    const ALL_OPTION = 'all';
    const CLEAN_OPTION = 'clean';
    const PAGE_SIZE_OPTION = 'page-size';

    protected $chunkGenerator;
    protected $categoryCollectionFactory;

    public function __construct(
        ChunkGenerator $chunkGenerator,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->chunkGenerator = $chunkGenerator;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('shatchi:chunk:generate')
            ->setDescription('Generate Redis Chunks for Category Pages')
            ->addOption(self::CATEGORY_OPTION, null, InputOption::VALUE_REQUIRED, 'Category ID')
            ->addOption(self::PAGE_SIZE_OPTION, null, InputOption::VALUE_OPTIONAL, 'Products per page', 25)
            ->addOption(self::ALL_OPTION, null, InputOption::VALUE_NONE, 'Generate for all categories')
            ->addOption(self::CLEAN_OPTION, null, InputOption::VALUE_NONE, 'Clean existing chunks');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $isAll = $input->getOption(self::ALL_OPTION);
        $isClean = $input->getOption(self::CLEAN_OPTION);
        $categoryId = $input->getOption(self::CATEGORY_OPTION);
    
        $categoryIds = [];
    
        if ($isAll) {
            $categories = $this->categoryCollectionFactory->create()
                ->addAttributeToFilter('is_active', 1)
                ->addAttributeToFilter('level', ['gteq' => 2]);
    
            foreach ($categories as $category) {
                $categoryIds[] = $category->getId();
            }
        } elseif ($categoryId) {
            $categoryIds[] = (int) $categoryId;
        } else {
            $output->writeln('<error>Please specify --category or use --all</error>');
            return Cli::RETURN_FAILURE;
        }
    
        foreach ($categoryIds as $id) {
            if ($isClean) {
                $this->chunkGenerator->clearCategoryCache($id);
                $output->writeln("<comment>Cleared Redis cache for category ID: $id</comment>");
            }
    
            $totalProducts = $this->chunkGenerator->generateProductsCache($id);
            $output->writeln("<info>Generated cache for category ID: $id (Total products: $totalProducts)</info>");
            
        }
    
        return Cli::RETURN_SUCCESS;
    }
    
}
