<?php

namespace Omniful\Core\Observer\Rma;

use Exception;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Rma\Api\RmaAttributesManagementInterface as RmaAttributeRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Model\ReturnOrder\Rma;
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
     * @var AttributeOptionInterfaceFactory
     */
    private $optionFactory;
    /**
     * @var Collection
     */
    private $attributeOptionCollection;
    /**
     * @var RmaAttributeRepositoryInterface
     */
    private $rmaAttributeRepository;
    /**
     * @var Rma
     */
    private $rma;

    /**
     * RmaSaveAfter constructor.
     * @param Logger $logger
     * @param Adapter $adapter
     * @param Rma $rma
     * @param StoreManagerInterface $storeManager
     * @param AttributeOptionInterfaceFactory $optionFactory
     * @param CollectionFactory $attributeOptionCollection
     * @param RmaAttributeRepositoryInterface $rmaAttributeRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Logger $logger,
        Adapter $adapter,
        Rma $rma,
        StoreManagerInterface $storeManager,
        AttributeOptionInterfaceFactory $optionFactory,
        CollectionFactory $attributeOptionCollection,
        RmaAttributeRepositoryInterface $rmaAttributeRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->storeManager = $storeManager;
        $this->optionFactory = $optionFactory;
        $this->attributeOptionCollection = $attributeOptionCollection;
        $this->rmaAttributeRepository = $rmaAttributeRepository;
        $this->rma = $rma;
    }

    /**
     * Execute
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        $rma = $observer->getEvent()->getDataObject();
        $rmaUpdateData = $this->rma->getReturnOrderData($rma);
        $rmaData = $rma->debug();
        unset($rmaData['items']);
        unset($rmaData['comments']);
        unset($rmaData['tracks']);
        $itemsStatus = [];
        $itemsClose = "closed";
        foreach ($rma->getItems() as $item) {
            $this->getResolutionAttributeValue($item);
            $itemsClose = $item->getStatus();
            $itemsStatus[] = $item->getStatus();
            $rmaData['items'][] = $item->debug();
        }
        foreach ($rma->getComments() as $comment) {
            $rmaData['comment'][] = $comment->debug();
        }
        foreach ($rma->getTracks() as $tracks) {
            $rmaData['tracks'][] = $tracks->debug();
        }

        $statusUnique = count(array_unique($itemsStatus));
        $orderId = $rmaData['order_id'];
        $status = $rmaData['status'];
        if ($status == "closed") {
            $status = $itemsClose;
        }
        if ($status == "approved_on_item") {
            $status = "partial_approved";
        }

        if ($status == "processed_closed") {
            $status = "approved";
        }
        if ($statusUnique == 2) {
            if (in_array("pending", $itemsStatus)) {
                foreach ($itemsStatus as $key => $value) {
                    if ($value == 'pending') {
                        unset($itemsStatus[$key]);
                    }
                }
                $status = "partial." . $itemsStatus[0];
            }
        } elseif ($status == "pending") {
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
                $rmaUpdateData,
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
     * Get Resolution Attribute Value
     *
     * @param mixed $item
     */
    public function getResolutionAttributeValue($item)
    {
        $attributes = $this->rmaAttributeRepository->getAllAttributesMetadata();
        $attributeCodes = [];
        foreach ($attributes as $attribute) {
            $options = $attribute->__toArray();
            $attributeCodes[] = $options['attribute_code'];
        }
        $updateData = [];
        foreach ($attributeCodes as $attributeCode) {
            $updateData[$attributeCode] = $item[$attributeCode];
        }
        foreach ($updateData as $key => $optionValue) {
            $optionFactory = $this->optionFactory->create();
            $optionFactory->load($optionValue);
            $attributeId = $optionFactory->getAttributeId();
            $optionData = $this->attributeOptionCollection->create()->setAttributeFilter($attributeId)
                ->setIdFilter($optionValue)
                ->setStoreFilter()
                ->load();
            foreach ($optionData as $option) {
                if ($updateData[$key] == $option->getOptionId()) {
                    $item[$key] = $option->getValue();
                }
            }
        }
    }
}
