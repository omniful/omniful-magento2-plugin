<?php

namespace Omniful\Core\Observer\Catalog;

use Omniful\Core\Logger\Logger;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Omniful\Core\Model\Adapter;
use Omniful\Core\Model\Catalog\Product as ProductManagement;

class ProductSaveAfter implements ObserverInterface
{
    const ORDER_CREATED_EVENT_NAME = "product.created";
    const ORDER_UPDATED_EVENT_NAME = "product.updated";

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ProductModel
     */
    protected $productModel;

    protected $adapter;
    protected $productManagement;

    /**
     * __construct
     *
     * @param Logger $logger
     * @param ProductModel $productModel
     */
    public function __construct(
        Logger $logger,
        Adapter $adapter,
        ProductModel $productModel,
        ProductManagement $productManagement
    ) {
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->productModel = $productModel;
        $this->productManagement = $productManagement;
    }

    /**
     * execute
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $event = $observer->getEvent();
        $eventName = $event->getName();

        switch ($eventName) {
            case "catalog_product_save_after":
                $this->updateSingle($observer);
                break;
            case "catalog_product_import_bunch_save_after":
                $this->updateBulk($observer);
                break;
            default:
                $this->logger->info("Invalid event: " . $eventName);
                break;
        }
    }

    /**
     * updateSingle
     *
     * @param Observer $observer
     * @return void
     */
    public function updateSingle(Observer $observer)
    {
        $product = $observer->getProduct();

        try {
            $this->routeFunctions($product);
        } catch (\Exception $e) {
            $this->logger->info(
                "Error while single updating : " . $e->getMessage()
            );
        }
    }

    /**
     * updateBulk
     *
     * @param Observer $observer
     * @return void
     */
    public function updateBulk(Observer $observer)
    {
        try {
            $productIds = $this->getProductIdsFromBunch($observer->getBunch());
            if ($productIds) {
                foreach ($productIds as $productId) {
                    $product = $this->productManagement->loadProductById(
                        $productId
                    );
                    $this->routeFunctions($product);
                }
            }
        } catch (\Exception $e) {
            $this->logger->info(
                "Error while bulk updating : " . $e->getMessage()
            );
        }
    }

    /**
     * updateSingle
     *
     * @param Product $product
     * @return void
     */
    public function routeFunctions($product)
    {
        try {
            if (
                $product === null ||
                $product->getId() === null ||
                $product->getCreatedAt() == $product->getUpdatedAt()
            ) {
                $this->handleProductCreated([$product]);
            } else {
                $this->handleProductUpdated([$product]);
            }
        } catch (\Exception $e) {
            $this->logger->info(
                "Error while single updating : " . $e->getMessage()
            );
        }
    }

    /**
     * handleProductCreated
     *
     * @param array $productIds
     * @return mixed|null
     */
    public function handleProductCreated(array $products): mixed
    {
        try {
            // CONNECT FIRST
            $this->adapter->connect();

            foreach ($products as $product) {
                $payload = $this->productManagement->getProductData($product);
                $response = $this->adapter->publishMessage(
                    self::ORDER_CREATED_EVENT_NAME,
                    $payload
                );

                if (!$response) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->info("Error while updating : " . $e->getMessage());
        }
    }

    /**
     * handleProductUpdated
     *
     * @param array $productIds
     * @return mixed|null
     */
    public function handleProductUpdated($products): mixed
    {
        try {
            // CONNECT FIRST
            $this->adapter->connect();

            foreach ($products as $product) {
                $payload = $this->productManagement->getProductData($product);
                $response = $this->adapter->publishMessage(
                    self::ORDER_UPDATED_EVENT_NAME,
                    $payload
                );

                if (!$response) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->info("Error while updating : " . $e->getMessage());
        }
    }

    /**
     * getProductIdsFromBunch
     *
     * @param array $bunch
     * @return array
     */
    public function getProductIdsFromBunch(array $bunch): array
    {
        $productIds = [];
        if (count($bunch)) {
            foreach ($bunch as $product) {
                $productIds[] = $this->productModel->getIdBySku(
                    $product["sku"]
                );
            }
        }

        return $productIds;
    }

    private function getUpdatedFields(
        array $newData,
        array $originalData
    ): array {
        $updatedFields = [];

        foreach ($newData as $key => $value) {
            if (isset($originalData[$key]) && $originalData[$key] !== $value) {
                $updatedFields[$key] = $value;
            }
        }

        return $updatedFields;
    }
}