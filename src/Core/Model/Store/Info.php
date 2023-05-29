<?php

namespace Omniful\Core\Model\Store;

use Omniful\Core\Api\Store\InfoInterface;
use Magento\Store\Model\StoreManagerInterface;

class Info implements InfoInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    protected $statusCollectionFactory;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $statusCollectionFactory
    ) {
        $this->storeManager = $storeManager;
        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    /**
     * Retrieve admin user and store information
     *
     * @return array
     */
    public function getAllStoreInfo(): array
    {
        try {
            $stores = $this->storeManager->getStores();
            $storeInfo = [];

            foreach ($stores as $store) {
                $storeInfo["stores"] = [
                    "store_id" => (int) $store->getId(),
                    "store_name" => (string) $store->getName(),
                    "store_code" => (string) $store->getCode(),
                    "store_website_id" => (int) $store->getWebsiteId(),
                    "store_website_name" => (string) $store
                        ->getWebsite()
                        ->getName(),
                    "store_group_id" => (int) $store->getGroupId(),
                    "store_group_name" => (string) $store
                        ->getGroup()
                        ->getName(),
                ];
            }

            // Retrieve all order statuses
            $orderStatuses = $this->getOrderStatuses();

            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => "Success",
                "data" => [
                    "store_info" => $storeInfo,
                    "order_statuses" => $orderStatuses,
                ],
            ];

            return $responseData;
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => (string) $e->getMessage(),
            ];

            return $responseData;
        }
    }

    /**
     * Retrieve all order statuses
     *
     * @return array
     */
    private function getOrderStatuses(): array
    {
        $orderStatuses = [];

        $statusCollection = $this->statusCollectionFactory->create();
        $statuses = $statusCollection->toOptionArray();

        foreach ($statuses as $status) {
            $orderStatuses[] = [
                "title" => $status["label"],
                "code" => $status["value"],
            ];
        }

        return $orderStatuses;
    }
}
