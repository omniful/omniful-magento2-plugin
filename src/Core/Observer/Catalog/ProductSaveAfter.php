<?php

namespace Omniful\Core\Observer\Catalog;

use Magento\Catalog\Model\Product as ProductModel;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Adapter;
use Omniful\Core\Model\Catalog\Product as ProductManagement;

class ProductSaveAfter implements ObserverInterface
{
    public const ORDER_CREATED_EVENT_NAME = "product.created";
    public const ORDER_UPDATED_EVENT_NAME = "product.updated";

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ProductModel
     */
    protected $productModel;
    /**
     * @var Adapter
     */
    protected $adapter;
    /**
     * @var ProductManagement
     */
    protected $productManagement;

    /**
     * ProductSaveAfter constructor.
     *
     * @param Logger            $logger
     * @param Adapter           $adapter
     * @param ProductModel      $productModel
     * @param ProductManagement $productManagement
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
     * Execute
     *
     * @param  Observer $observer
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
     * UpdateSingle
     *
     * @param  Observer $observer
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
     * UpdateSingle
     *
     * @param  Product $product
     * @return void
     */
    public function routeFunctions($product)
    {
        try {
            if ($product === null
                || $product->getId() === null
                || $product->getCreatedAt() == $product->getUpdatedAt()
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
     * HandleProductCreated
     *
     * @param  array $products
     * @return mixed
     */
    public function handleProductCreated(array $products)
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
     * HandleProductUpdated
     *
     * @param  array $products
     * @return mixed
     */
    public function handleProductUpdated($products)
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
     * UpdateBulk
     *
     * @param  Observer $observer
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
     * GetProductIdsFromBunch
     *
     * @param  array $bunch
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
}
