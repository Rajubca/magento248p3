<?php
namespace Shatchi\TradeAccount\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class CustomerWebsite extends Column
{
    public function __construct(
        \Magento\Framework\View\Element\UiComponent\ContextInterface $context,
        \Magento\Framework\View\Element\UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item[$this->getData('name')] = $item['customer_website'] ?? '';
            }
        }
        return $dataSource;
    }
}