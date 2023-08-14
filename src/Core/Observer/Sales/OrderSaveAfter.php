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
    public const ORDER_CREATED_EVENT_NAME = "order.created";
    public const ORDER_UPDATED_EVENT_NAME = "order.updated";
    public const ORDER_STATUS_UPDATED_EVENT_NAME = "order.status.updated";

    public const ALLOWED_STATUSES = [
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
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var Adapter
     */
    protected $adapter;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var OrderManagement
     */
    protected $orderManagement;

    /**
     * OrderSaveAfter constructor.
     *
     * @param Logger                $logger
     * @param Adapter               $adapter
     * @param OrderManagement       $orderManagement
     * @param StoreManagerInterface $storeManager
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
     * Triggers when an order is saved and sending webhook message to omniful
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getOrder();
        $store = $order->getStore();

        try {
            // Check if the order status is "complete" and update the status to "delivered"
            if ($order->getStatus() === Order::STATE_COMPLETE) {
                $order->setStatus(Status::STATUS_DELIVERED);
                $order->save();
            }

            // Determine the event name based on order status changes
            $eventName = $this->getEventName($order);
            $storeData = $this->storeManager->getGroup($store->getGroupId());

            $headers = [
                "x-website-code" => $order
                    ->getStore()
                    ->getWebsite()
                    ->getCode(),
                "x-store-code" => $storeData->getCode(),
                "x-store-view-code" => $order->getStore()->getCode(),
            ];

            // Connect to the adapter
            $this->adapter->connect();

            // Publish the event if the event name is not empty
            if ($eventName !== "") {
                $payload = $this->orderManagement->getOrderData($order);
                // Log the successful publication of the order event
                $this->logger->info(__("Order event published successfully"));
                return $this->adapter->publishMessage(
                    $eventName,
                    $payload,
                    $headers
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(__($e->getMessage()));
        }
    }

    /**
     * Determine the event name based on order status changes
     *
     * @param Order $order
     * @return string
     */
    private function getEventName(Order $order)
    {
        $eventName = "";
        if ($order->getOrigData("status") === null &&
            $order->getStatus() !== Order::STATE_CANCELED
        ) {
            $eventName = self::ORDER_CREATED_EVENT_NAME;
        } elseif ($order->getStatus() !== Order::STATE_CANCELED &&
            $order->getStatus() !== $order->getOrigData("status") &&
            in_array($order->getStatus(), self::ALLOWED_STATUSES)
        ) {
            $eventName = self::ORDER_STATUS_UPDATED_EVENT_NAME;
        }
        return $eventName;
    }
}
