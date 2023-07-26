<?php

namespace Omniful\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Omniful\Core\Model\Adapter;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Sales\Order as OrderManagement;

class OrderSaveAfter implements ObserverInterface
{

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
     * OrderSaveAfter constructor.
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
     * Triggers when an order is saved and performs necessary actions based on order status
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        try {
            // Determine the event name based on order status changes
            $eventName = 'order.created';

            // Connect to the adapter
            $this->adapter->connect();

            // Publish the event if the event name is not empty
            if ($eventName !== "") {
                $payload = $this->orderManagement->getOrderData($order);
                // Log the successful publication of the order event
                $this->logger->info('Order event published successfully');
                return $this->adapter->publishMessage(
                    $eventName,
                    $payload
                );
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
