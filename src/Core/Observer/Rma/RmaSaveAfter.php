<?php

namespace Omniful\Core\Observer\Rma;

use Exception;
use Magento\Framework\App\Request\Http;
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
     * @var Http
     */
    private $request;
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
     * @param Http $request
     * @param Logger $logger
     * @param Adapter $adapter
     * @param StoreManagerInterface $storeManager
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Http $request,
        Logger $logger,
        Adapter $adapter,
        StoreManagerInterface $storeManager,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->request = $request;
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
        $rma = $observer->getEvent()->getDataObject()->debug();
        $rmaData["items"][0] = $rma;
        $rmaItemData = $rma["items"];
        $rmaCommentData = $rma["comments"];
        unset($rmaData["items"][0]["items"]);
        unset($rmaData['items'][0]["comments"]);
        $itemStatus = [];
        $rmaData = $this->manageRmaDataItems($rmaItemData, $rmaCommentData, $rmaData);
        $orderId = $this->request->getParam('order_id');
        try {
            if (empty($orderId)) {
                $items = $this->request->getParam('items');
                foreach ($items as $item) {
                    $itemStatus[] = $item['status'];
                    $orderId = $item['order_item_id'];
                }
            }
            $order = $this->orderRepository->get($orderId);
            $store = $order->getStore();
            $uniqItemsStatus = array_unique($itemStatus);
            if (empty($itemStatus)) {
                $eventName = 'rma.create';
            } elseif (count($uniqItemsStatus) == 1) {
                foreach ($itemStatus as $status) {
                    if (in_array($status, self::EVENT_NAME)) {
                        $eventName = $status;
                    } else {
                        $eventName = 'rma.create';
                    }
                }
            } else {
                $eventName = 'rma.partial';
            }
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
                $eventName,
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

    /**
     * Manage items and return updated items
     *
     * @param mixed $itemData
     * @param mixed $rmaCommentData
     * @param mixed $rmaData
     * @return mixed
     */
    public function manageRmaDataItems($itemData, $rmaCommentData, $rmaData)
    {
        foreach ($rmaCommentData as $comment) {
            $rmaData['items'][0]["comments"][] = $comment;
        }
        foreach ($itemData as $item) {
            $rmaData["items"][0]["items"][] = $item;
        }

        return $rmaData;
    }
}
