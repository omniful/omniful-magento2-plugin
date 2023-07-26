<?php

namespace Omniful\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Omniful\Core\Model\Adapter;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Sales\Order as OrderManagement;

class OrderPlaceAfter implements ObserverInterface
{
    public const ORDER_CREATED_EVENT_NAME = "order.created";

    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var Adapter
     */
    protected $adapter;
    /**
     * @var OrderManagement
     */
    protected $orderManagement;

    /**
     * OrderPlaceAfter constructor.
     *
     * @param Logger                $logger
     * @param Adapter               $adapter
     * @param OrderManagement       $orderManagement
     */
    public function __construct(
        Logger $logger,
        Adapter $adapter,
        OrderManagement $orderManagement
    ) {
        $this->logger = $logger;
        $this->adapter = $adapter;
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
        $order = $observer->getEvent()->getOrder();
        try {
            $eventName = self::ORDER_CREATED_EVENT_NAME;
            $headers = [
                "website_code" => $order->getStore()->getWebsite()->getCode(),
                "store_code" => $order->getStore()->getCode(),
                "store_view_code" => $order->getStore()->getName(),
            ];

            // Connect to the adapter
            $this->adapter->connect();

            // Publish the event if the event name is not empty
            if ($eventName !== "") {
                $payload = $this->orderManagement->getOrderData($order);
                // Log the successful publication of the order event
                $this->logger->info('Order event published successfully');
                return $this->adapter->publishMessage(
                    $eventName,
                    $payload,
                    $headers
                );
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}