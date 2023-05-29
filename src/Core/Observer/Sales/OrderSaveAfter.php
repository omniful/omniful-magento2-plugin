<?php

namespace Omniful\Core\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Omniful\Core\Model\Adapter;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Sales\Order as OrderManagement;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Order;
use Omniful\Core\Model\Sales\Status;

class OrderSaveAfter implements ObserverInterface
{
    const ORDER_CREATED_EVENT_NAME = "order.created";
    const ORDER_UPDATED_EVENT_NAME = "order.updated";
    const ORDER_STATUS_UPDATED_EVENT_NAME = "order.status.updated";

    const ALLOWED_STATUSES = [
        Order::STATE_NEW,
        Order::STATE_PENDING_PAYMENT,
        Order::STATE_PROCESSING,
        Status::STATUS_READY_TO_SHIP,
        Status::STATUS_SHIPPED,
        Status::STATUS_REFUNDED,
        Status::STATUS_PACKED,
        Status::STATUS_DELIVERED,
        Order::STATE_COMPLETE,
    ];

    protected $logger;
    protected $adapter;
    protected $storeManager;
    protected $orderManagement;

    /**
     * Constructor Injection
     *
     * @param \Omniful\Core\Logger\Logger $logger
     * @param \Omniful\Core\Model\Adapter $adapter
     * @param \Omniful\Core\Model\Sales\Order $orderManagement
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        Logger $logger,
        Adapter $adapter,
        OrderManagement $orderManagement,
        StoreManagerInterface $storeManager
    ) {
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->storeManager = $storeManager;
        $this->orderManagement = $orderManagement;
    }

    /**
     * Triggers when an order is saved and performs necessary actions based on order status
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getOrder();

        try {
            // Check if the order status is "complete" and update the status to "delivered"
            if ($order->getStatus() === Order::STATE_COMPLETE) {
                $order->setStatus(Status::STATUS_DELIVERED);
                $order->save();
            }

            // Determine the event name based on order status changes
            $eventName = $this->getEventName($order);

            // Connect to the adapter
            $this->adapter->connect();

            // Publish the event if the event name is not empty
            if ($eventName !== "") {
                $payload = $this->orderManagement->getOrderData($order);
                $response = $this->adapter->publishMessage(
                    $eventName,
                    $payload
                );

                // Log the successful publication of the order event
                // $this->logger->info('Order event published successfully');

                return $response;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Determine the event name based on order status changes
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    private function getEventName(Order $order)
    {
        $eventName = "";

        if (
            $order->getOrigData("status") === null &&
            $order->getStatus() !== Order::STATE_CANCELED
        ) {
            $eventName = self::ORDER_CREATED_EVENT_NAME;
        } elseif (
            $order->getStatus() !== Order::STATE_CANCELED &&
            $order->getStatus() !== $order->getOrigData("status") &&
            in_array($order->getStatus(), self::ALLOWED_STATUSES)
        ) {
            $eventName = self::ORDER_STATUS_UPDATED_EVENT_NAME;
        }

        return $eventName;
    }
}
