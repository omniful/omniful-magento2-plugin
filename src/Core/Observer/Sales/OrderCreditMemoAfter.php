<?php

namespace Omniful\Core\Observer\Sales;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Omniful\Core\Model\Sales\Status;
use Magento\Sales\Model\Order;

class SalesOrderCreditMemoAfter implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        try {
            $shipment = $observer->getEvent()->getCreditmemo();
            $order = $shipment->getOrder();

            if (!$order->getIsVirtual()) {
                $order
                    ->setState(Order::STATE_CLOSED)
                    ->setStatus(Status::STATUS_REFUNDED);
                $order->save();
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
