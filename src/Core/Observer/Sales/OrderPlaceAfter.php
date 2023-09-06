<?php

namespace Omniful\Core\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Omniful\Core\Model\Adapter;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Sales\Order as OrderManagement;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * OrderPlaceAfter constructor.
     *
     * @param Logger $logger
     * @param Adapter $adapter
     * @param OrderManagement $orderManagement
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
        $order = $observer->getEvent()->getOrder();
        $store = $order->getStore();
        // TODO Remove this log before production
        $this->logger->info("Cancellation request received for Event: order Created, Order: " . json_encode($order));

        try {
            $eventName = self::ORDER_CREATED_EVENT_NAME;
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
}
