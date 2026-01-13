<?php

namespace Shatchi\TradeAccount\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Customer\Setup\CustomerSetupFactory;

class AddCustomerAttributes implements DataPatchInterface
{
    private $moduleDataSetup;
    private $eavSetupFactory;
    private $customerSetupFactory; // ✅ Define the missing property

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory,
        CustomerSetupFactory $customerSetupFactory // ✅ Add it to the constructor
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->customerSetupFactory = $customerSetupFactory; // ✅ Assign it
    }

    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Add 'total_outlets' attribute
        $eavSetup->addAttribute(
            Customer::ENTITY,
            'total_outlets',
            [
                'type' => 'int',
                'label' => 'Total Outlets',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'system' => false,
                'position' => 100,
            ]
        );

        // Add 'customers_message' attribute
        $eavSetup->addAttribute(
            Customer::ENTITY,
            'customers_message',
            [
                'type' => 'text',
                'label' => 'Customers Message',
                'input' => 'textarea',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'system' => false,
                'position' => 101,
            ]
        );
        // Add 'website' attribute
        $eavSetup->addAttribute(
            Customer::ENTITY,
            'customer_website',
            [
                'type' => 'text',
                'label' => 'Customer Website',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'user_defined' => true,
                'system' => false,
                'position' => 102,
            ]
        );
        // Enable attributes in forms
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributes = ['total_outlets', 'customers_message', 'customer_website'];
        foreach ($attributes as $attributeCode) {
            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $attributeCode);
            $attribute->setData(
                'used_in_forms',
                ['adminhtml_customer', 'customer_account_create', 'customer_account_edit']
            );
            $attribute->save();
        }
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
