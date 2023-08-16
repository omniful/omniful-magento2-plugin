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
        Logger $logger,
        Adapter $adapter,
        ProductModel $productModel,
        WebsiteRepositoryInterface $websiteRepository,
        ProductRepository $productRepository,
        StoreManagerInterface $storeManager,
        ProductManagement $productManagement
    ) {
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
            $code = $product->getStore()->getCode();
            $eventName = $this->isNewProduct($product)
                ? self::PRODUCT_CREATED_EVENT_NAME
                : self::PRODUCT_UPDATED_EVENT_NAME;

            // Connect to the adapter
            $this->adapter->connect();
            if ($code == 'admin') {
                foreach ($product->getWebsiteIds() as $websiteId) {
                    $website = $this->websiteRepository->get($websiteId);
                    $websiteCode = $website->getCode();
                    $storeGroup = $this->storeManager->getWebsite($websiteId)->getGroups();
                    foreach ($storeGroup as $store) {
                        $storeCode = $store->getCode();
                        foreach ($store->getStores() as $storeView) {
                            $storeViewCode = $storeView->getCode();
                            // Check if the product is new or updated
                            $headers = [
                                "x-website-code" => $websiteCode,
                                "x-store-code" => $storeCode,
                                "x-store-view-code" => $storeViewCode,
                            ];
                            // Publish the event
                            $product = $this->productRepository
                                ->getById($product->getId(), false, $store->getId());
                            $payload = $this->productManagement->getProductFullData($product);
                            $response = $this->adapter->publishMessage(
                                $eventName,
                                $payload,
                                $headers
                            );

                        }
                    }
                }
            } else {
                $headers = $this->getHeader($product);
                $this->adapter->connect();
                $payload = $this->productManagement->getProductFullData($product);
                $this->adapter->publishMessage(
                    $eventName,
                    $payload,
                    $headers
                );
            }
            if (!$response) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            $this->logger->info(
                __("Error while updating product: " . $e->getMessage())
            );
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
        return $product->getId() === null ||
            $product->getCreatedAt() == $product->getUpdatedAt();
    }

    /**
     * Get Header
     *
     * @param array $product
     * @return array
     */
    public function getHeader($product)
    {
        $code = $product->getStore()->getCode();
        return [
            "x-website-code" => $code == 'admin' ? 'default' : $product
                ->getStore()
                ->getWebsite()
                ->getCode(),
            "x-store-code" => $code == 'admin' ? 'default' : $product->getStore()->getCode(),
            "x-store-view-code" => $code == 'admin' ? 'default' : $product->getStore()->getName(),
        ];
    }
}
