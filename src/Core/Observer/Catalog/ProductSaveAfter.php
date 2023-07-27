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
    public const PRODUCT_CREATED_EVENT_NAME = "product.created";
    public const PRODUCT_UPDATED_EVENT_NAME = "product.updated";

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
        $product = $observer->getProduct();

        try {
            // Check if the product is new or updated
            $eventName = $this->isNewProduct($product)
                ? self::PRODUCT_CREATED_EVENT_NAME
                : self::PRODUCT_UPDATED_EVENT_NAME;
            $headers = [
                "x-website-code" => $product->getStore()->getWebsite()->getCode(),
                "x-store-code" => $product->getStore()->getCode(),
                "x-store-view-code" => $product->getStore()->getName(),
            ];

            // Connect to the adapter
            $this->adapter->connect();

            // Publish the event
            $payload = $this->productManagement->getProductData($product);
            $response = $this->adapter->publishMessage($eventName, $payload, $headers);

            if (!$response) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->info("Error while updating product: " . $e->getMessage());
        }
    }

    /**
     * Check if the product is new or updated
     *
     * @param ProductModel $product
     * @return bool
     */
    private function isNewProduct(ProductModel $product): bool
    {
        return $product->getId() === null || $product->getCreatedAt() == $product->getUpdatedAt();
    }
}