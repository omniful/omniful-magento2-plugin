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
        $eventName = null;
        $productIds = $this->getProductIdsFromBunch($observer->getEvent()->getProductIds());

        try {
            // Connect to the adapter
            $this->adapter->connect();

            if ($productIds) {
                foreach ($productIds as $productId) {
                    $product = $this->productManagement->loadProductById($productId);

                    if (
                        $product === null
                        || $product->getId() === null
                        || $product->getCreatedAt() == $product->getUpdatedAt()
                    ) {
                        $eventName = self::PRODUCT_CREATED_EVENT_NAME;
                    } else {
                        $eventName = self::PRODUCT_UPDATED_EVENT_NAME;
                    }

                    $payload = $this->productManagement->getProductData($product);
                    $response = $this->adapter->publishMessage($eventName, $payload);

                    if (!$response) {
                        return false;
                    }
                }

                return true;
            }
        } catch (\Exception $e) {
            $this->logger->info("Error while updating: " . $e->getMessage());
        }
    }

    /**
     * GetProductIdsFromBunch
     *
     * @param  array $productIds
     * @return array
     */
    public function getProductIdsFromBunch(array $productIds): array
    {
        $validProductIds = [];
        if (!empty($productIds)) {
            foreach ($productIds as $productId) {
                $product = $this->productModel->load($productId);
                if ($product && $product->getId()) {
                    $validProductIds[] = $product->getId();
                }
            }
        }

        return $validProductIds;
    }
}