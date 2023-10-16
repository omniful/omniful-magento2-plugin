<?php

namespace Omniful\Core\Observer\Rma;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Adapter;

class RmaSaveAfter implements ObserverInterface
{

    public const EVENT_NAME = [
        "authorized",
        "partially_authorized",
        "received",
        "received_on_item",
        "approved",
        "approved_on_item",
        "rejected",
        "rejected_on_item",
        "denied",
        "closed",
        "processed_closed"
    ];

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var Adapter
     */
    private $adapter;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * RmaSaveAfter constructor.
     * @param Logger $logger
     * @param Adapter $adapter
     * @param StoreManagerInterface $storeManager
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Logger $logger,
        Adapter $adapter,
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $rma = $observer->getEvent()->getDataObject();
        $rmaData = $rma->debug();
        unset($rmaData['items']);
        unset($rmaData['comments']);
        unset($rmaData['tracks']);
        $itemsStatus = "closed";
        foreach ($rma->getItems() as $item) {
            $itemsStatus = $item->getStatus();
            $rmaData['items'][] = $item->debug();
        }
        foreach ($rma->getComments() as $comment) {
            $rmaData['comment'][] = $comment->debug();
        }
        foreach ($rma->getTracks() as $tracks) {
            $rmaData['tracks'][] = $tracks->debug();
        }

        $orderId = $rmaData['order_id'];
        $status = $rmaData['status'];
        if ($status == "closed") {
            $status = $itemsStatus;
        }
        if ($status == "pending") {
            $status = "create";
        }
        try {
            $order = $this->orderRepository->get($orderId);
            $store = $order->getStore();
            $storeData = $this->storeManager->getGroup($store->getGroupId());
            $headers = [
                "website-code" => $order
                    ->getStore()
                    ->getWebsite()
                    ->getCode(),
                "x-store-code" => $storeData->getCode(),
                "x-store-view-code" => $order->getStore()->getCode(),
            ];
            // CONNECT FIRST
            $this->adapter->connect();
            // PUSH CANCEL ORDER EVENT
            $response = $this->adapter->publishMessage(
                "rma." . $status,
                $rmaData,
                $headers
            );
            // LOG MESSAGE
            $this->logger->info(__("Order Rma successfully"));
            return $response;
        } catch (Exception $e) {
            $this->logger->info($e->getMessage());
        }
    }
}
