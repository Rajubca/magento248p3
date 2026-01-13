<?php
namespace Shatchi\BannerSlider5\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        $tableName = $installer->getTable('shatchi_bannerslider5_config');
        if (!$installer->tableExists($tableName)) {
            $table = $installer->getConnection()->newTable($tableName)
                ->addColumn(
                    'slides_json',
                    Table::TYPE_TEXT,
                    '2M',
                    ['nullable' => false],
                    'Serialized Slide JSON Data'
                )
                ->setComment('BannerSlider5 Single Slider Config Table');

            $installer->getConnection()->createTable($table);
        }

        $installer->endSetup();
    }
}
