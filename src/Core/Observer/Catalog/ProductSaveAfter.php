<?php

namespace Omniful\Core\Observer\Catalog;

use Exception;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
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
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;
    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * ProductSaveAfter constructor.
     *
     * @param Logger $logger
     * @param Adapter $adapter
     * @param ProductModel $productModel
     * @param WebsiteRepositoryInterface $websiteRepository
     * @param ProductRepository $productRepository
     * @param StoreManagerInterface $storeManager
     * @param ProductManagement $productManagement
     */
    public function __construct(
        Logger                     $logger,
        Adapter                    $adapter,
        ProductModel               $productModel,
        WebsiteRepositoryInterface $websiteRepository,
        ProductRepository          $productRepository,
        StoreManagerInterface      $storeManager,
        ProductManagement          $productManagement
    )
    {
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->websiteRepository = $websiteRepository;
        $this->productModel = $productModel;
        $this->productManagement = $productManagement;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
    }

    /**
     * Execute
     *
     * @param Observer $observer
     * @return bool
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getProduct();
        $response = '';
        try {
            $payload = $this->productManagement->getProductInfo($product);

            // Retrieve website IDs
            $websiteIds = $product->getWebsiteIds();

            foreach ($websiteIds as $websiteId) {
                // Get stores for each website
                $website = $this->storeManager->getWebsite($websiteId);
                $stores = $website->getStores();

                foreach ($stores as $store) {
                    try {
                        $this->publishProductData($website, $store, $payload);
                    } catch (Exception $e) {
                        $this->logger->error(__($e->getMessage()));
                    }

                }
            }
        } catch (Exception $e) {
            $this->logger->info(
                __("Error while updating product: " . $e->getMessage())
            );
        }
    }


    private function publishProductData($website, $store, $payload)
    {
        $headers = [
            "x-website-code" => $website->getCode(),
            "x-store-code" => $store->getCode(),
            "x-store-view-code" => $store->getCode(),
        ];

        // Connect to the adapter
        $this->adapter->connect($store->getId());
        $this->adapter->publishMessage(
            "product.event",
            $payload,
            $headers
        );
    }


    /**
     * Check if the product is new or updated
     *
     * @param ProductModel $product
     * @return bool
     */
    private function isNewProduct(ProductModel $product): bool
    {
        return $product->getId() === null ||
            $product->getCreatedAt() == $product->getUpdatedAt();
    }

}
