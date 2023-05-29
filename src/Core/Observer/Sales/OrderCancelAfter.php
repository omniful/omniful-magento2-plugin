<?php

namespace Omniful\Core\Observer\Sales;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Omniful\Core\Model\Adapter;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Sales\Order as OrderManagement;
use Magento\Store\Model\StoreManagerInterface;

class OrderCancelAfter implements ObserverInterface
{
    const EVENT_NAME = "order.canceled";

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
     * Triggers when an order is canceled and initiates the adapter order cancel function
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getOrder();

        try {
            if ($order->getStatus() == "canceled") {
                // CONNECT FIRST
                $this->adapter->connect();

                // PUSH CANCEL ORDER EVENT
                $payload = $this->orderManagement->getOrderData($order);
                $response = $this->adapter->publishMessage(
                    self::EVENT_NAME,
                    $payload
                );

                // LOG MESSAGE
                // $this->logger->info('Order Canceled successfully');

                return $response;
            }
        } catch (\Exception $e) {
            var_dump($e->getMessage());
            exit();
            // $this->logger->info($e->getMessage());
        }
    }
}
