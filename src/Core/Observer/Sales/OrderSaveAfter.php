<?php

namespace Omniful\Core\Observer\Sales;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Adapter;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Omniful\Core\Model\Sales\Order as OrderManagement;

class OrderSaveAfter implements ObserverInterface
{
    public const ORDER_EVENT = "order.event";

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
     * @var DateTime
     */
    private $dateTime;

    /**
     * OrderPlaceAfter constructor.
     *
     * @param Logger $logger
     * @param Adapter $adapter
     * @param OrderManagement $orderManagement
     * @param DateTime $dateTime
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Logger                $logger,
        Adapter               $adapter,
        OrderManagement       $orderManagement,
        DateTime              $dateTime,
        StoreManagerInterface $storeManager
    )
    {
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->storeManager = $storeManager;
        $this->orderManagement = $orderManagement;
        $this->dateTime = $dateTime;
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
        try {
            $eventName = self::ORDER_EVENT;
            return $this->getOrderDataByEventName($store, $order, $eventName);
        } catch (Exception $e) {
            $this->logger->error(__($e->getMessage()));
        }
    }

    /**
     * Get Order Data By Event Name
     *
     * @param mixed $store
     * @param mixed $order
     * @param mixed $eventName
     * @throws LocalizedException
     */
    public function getOrderDataByEventName($store, $order, $eventName)
    {
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
            $payload = $this->orderManagement->getOrderInfo($order);
            // Log the successful publication of the order event
            $this->logger->info(__("Order event published successfully"));
            return $this->adapter->publishMessage(
                $eventName,
                $payload,
                $headers
            );
        }
    }
}
