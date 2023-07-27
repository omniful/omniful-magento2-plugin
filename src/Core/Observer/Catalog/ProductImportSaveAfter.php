<?php

namespace Omniful\Core\Observer\Catalog;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Adapter;
use Omniful\Core\Model\Catalog\Product as ProductManagement;

class ProductImportSaveAfter implements ObserverInterface
{
    public const PRODUCT_CREATED_EVENT_NAME = "product.created";
    public const PRODUCT_UPDATED_EVENT_NAME = "product.updated";

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * @var ProductManagement
     */
    protected $productManagement;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * ProductSaveAfter constructor.
     *
     * @param Logger $logger
     * @param Adapter $adapter
     * @param ProductRepositoryInterface $productRepository
     * @param ProductManagement $productManagement
     */
    public function __construct(
        Logger $logger,
        Adapter $adapter,
        ProductRepositoryInterface $productRepository,
        ProductManagement $productManagement
    ) {
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->productManagement = $productManagement;
        $this->productRepository = $productRepository;
    }

    /**
     * Execute
     *
     * @param Observer $observer
     * @return bool
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $eventName = null;
        $productIds = $this->getProductIdsFromBunch($observer->getEvent()->getData('bunch'));

        try {
            // Connect to the adapter
            $this->adapter->connect();

            if ($productIds) {
                foreach ($productIds as $productId) {
                    $product = $this->productManagement->loadProductById($productId);

                    if ($product === null
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
        } catch (Exception $e) {
            $this->logger->info("Error while updating: " . $e->getMessage());
        }
    }

    /**
     * GetProductIdsFromBunch
     *
     * @param array $productsBunch
     * @return array
     * @throws NoSuchEntityException
     */
    public function getProductIdsFromBunch(array $productsBunch): array
    {
        $validProductIds = [];
        if (!empty($productsBunch)) {
            foreach ($productsBunch as $productBunch) {
                $product = $this->productRepository->get($productBunch['sku']);
                if ($product && $product->getId()) {
                    $validProductIds[] = $product->getId();
                }
            }
        }
        return $validProductIds;
    }
}
