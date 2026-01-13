<?php
namespace Shatchi\BannerSlider5\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();

        if (!$installer->tableExists('shatchi_bannerslider5_config')) {
            $table = $installer->getConnection()->newTable(
                $installer->getTable('shatchi_bannerslider5_config')
            )->addColumn(
                'slides_json',
                Table::TYPE_TEXT,
                '2M',
                ['nullable' => false],
                'Slider JSON'
            );

            $installer->getConnection()->createTable($table);
        }

        $installer->endSetup();
    }
}
