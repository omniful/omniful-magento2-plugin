<?php

namespace Omniful\Core\Observer\Sales;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Omniful\Core\Model\Sales\Status;
use Magento\Sales\Model\Order;

class OrderCreditMemoAfter implements ObserverInterface
{
    /**
     * Execute
     *
     * @param  Observer $observer
     * @return string|void
     */
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
            return __($e->getMessage());
        }
    }
}
