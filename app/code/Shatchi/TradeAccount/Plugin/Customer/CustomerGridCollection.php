<?php

namespace Shatchi\TradeAccount\Plugin\Customer;



class CustomerGridCollection
{
    public function beforeLoad(
        \Magento\Customer\Model\ResourceModel\Grid\Collection $subject
    ) {
        if (!$subject->isLoaded()) {
            $connection = $subject->getConnection();
            $tableInt = $subject->getTable('customer_entity_int');
            $tableText = $subject->getTable('customer_entity_text');

            // Get attribute IDs
            $eavAttribute = $connection->fetchPairs(
                "SELECT attribute_code, attribute_id FROM eav_attribute WHERE entity_type_id = (SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code = 'customer')"
            );

            // Join total_outlets (int)
            $subject->getSelect()->joinLeft(
                ['total_outlets_table' => $tableInt],
                "main_table.entity_id = total_outlets_table.entity_id AND total_outlets_table.attribute_id = " . $eavAttribute['total_outlets'],
                ['total_outlets' => 'total_outlets_table.value']
            );

            // Join customers_message (text)
            $subject->getSelect()->joinLeft(
                ['customers_message_table' => $tableText],
                "main_table.entity_id = customers_message_table.entity_id AND customers_message_table.attribute_id = " . $eavAttribute['customers_message'],
                ['customers_message' => 'customers_message_table.value']
            );

            // Join customer_website (text)
            $subject->getSelect()->joinLeft(
                ['customer_website_table' => $tableText],
                "main_table.entity_id = customer_website_table.entity_id AND customer_website_table.attribute_id = " . $eavAttribute['customer_website'],
                ['customer_website' => 'customer_website_table.value']
            );
        }
    }
}
