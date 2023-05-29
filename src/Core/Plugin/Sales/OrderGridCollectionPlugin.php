<?php
namespace Omniful\Core\Plugin\Sales;

use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;

class OrderGridCollectionPlugin
{
    public function afterGetSearchResult(OrderGridCollection $subject, $result)
    {
        $result
            ->getSelect()
            ->joinLeft(
                ["order_shipment" => $result->getTable("sales_shipment")],
                "main_table.entity_id = order_shipment.order_id",
                [
                    "fulfillment_status" =>
                        'IF(order_shipment.entity_id IS NULL, "Not Fulfilled", "Fulfilled")',
                ]
            );

        return $result;
    }

    public function afterPrepareEntity(
        OrderGridCollection $subject,
        $result,
        Document $document
    ) {
        $fulfillmentStatus = $document->getData("fulfillment_status");
        $document->setData("fulfillment_status", $fulfillmentStatus);

        return $result;
    }
}
