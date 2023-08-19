<?php

namespace Omniful\Core\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class OrderSaveBefore implements ObserverInterface
{
    public function __construct()
    {
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $order->setData('custom_order_attribute', 25);
    }
}
