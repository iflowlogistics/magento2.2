<?php
/**
 * Created by PhpStorm.
 * User: Pablo Garcia
 * Email: pablolgarcia@gmail.com
 * Date: 29/08/18
 * Time: 22:07
 */

namespace Iflow\IflowShipping\Plugin\Adminhtml\Order;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as SalesOrderGridCollection;

class FilterGridCollection
{
    private $messageManager;
    private $collection;

    public function __construct(
        SalesOrderGridCollection $collection
    )
    {
        $this->collection = $collection;
    }
    public function afterGetReport(
        $subject,
        $collection,
        $requestName
    ) {
        if ($requestName == 'iflow_iflowshipping_order_listing_data_source') {
            $select = $collection->getSelect();

            $select->join(
                ["so" => $collection->getTable("sales_order")],
                'main_table.entity_id = so.entity_id AND so.status != "canceled" AND so.shipping_method IN ("iflow_iflow")',
                array('shipping_method')
            );

            $select->joinLeft(
                ["ss" => $collection->getTable("sales_shipment")],
                'main_table.entity_id = ss.order_id',
                array('shipping_label')
            );

            $select->where('ss.shipping_label IS NULL');

            $collection->addFilterToMap('entity_id','main_table.entity_id');
        }
        return $collection;
    }
}
