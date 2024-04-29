<?php

namespace Omniful\Core\Model\Catalog;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product as MagentoProduct;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Api\Catalog\ProductInterface;
use Omniful\Core\Helper\Data;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;

class Product implements ProductInterface
{
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var Http
     */
    protected $request;
    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;
    /**
     * @var AttributeRepositoryInterface
     */
    protected $attributeRepository;
    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;
    /**
     * @var Configurable
     */
    protected $configurableProductType;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var ProductInterfaceFactory
     */
    private $productFactory;
    /**
     * @var SourceItemsSaveInterface
     */
    private $sourceItemsSave;
    /**
     * @var SourceItemInterface
     */
    private $sourceItem;
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Product constructor.
     *
     * @param Http $request
     * @param StockRegistryInterface $stockRegistry
     * @param Configurable $configurableProductType
     * @param Data $helper
     * @param StoreRepositoryInterface $storeRepository
     * @param StoreManagerInterface $storeManager
     * @param AttributeRepositoryInterface $attributeRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ProductRepositoryInterface $productRepository
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param SourceItemInterface $sourceItem
     * @param ProductInterfaceFactory $productFactory
     */
    public function __construct(
        Http $request,
        StockRegistryInterface $stockRegistry,
        Configurable $configurableProductType,
        Data $helper,
        StoreRepositoryInterface $storeRepository,
        StoreManagerInterface $storeManager,
        AttributeRepositoryInterface $attributeRepository,
        CategoryRepositoryInterface $categoryRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepositoryInterface $productRepository,
        SourceItemsSaveInterface $sourceItemsSave,
        SourceItemInterface $sourceItem,
        ProductInterfaceFactory $productFactory
    ) {
        $this->stockRegistry = $stockRegistry;
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->configurableProductType = $configurableProductType;
        $this->categoryRepository = $categoryRepository;
        $this->request = $request;
        $this->sourceItemsSave = $sourceItemsSave;
        $this->sourceItem = $sourceItem;
        $this->helper = $helper;
        $this->storeRepository = $storeRepository;
        $this->storeManager = $storeManager;
    }

    /**
     * Get Products
     *
     * @return mixed|string[]
     */
    public function getProducts()
    {
        try {
            $page = (int)$this->request->getParam("page") ?: 1;
            $limit = (int)$this->request->getParam("limit") ?: 200;
            $searchCriteria = $this->createSearchCriteria($page, $limit);
            $searchResults = $this->productRepository->getList($searchCriteria);
            $products = $searchResults->getItems();
            $totalProducts = $searchResults->getTotalCount(); // Total products count
            $productData = $this->getEnableProductData($products);
            $pageInfo = [
                "current_page" => $page,
                "per_page" => $limit,
                "total_count" => $totalProducts,
                "total_pages" => ceil($totalProducts / $limit),
            ];
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $productData,
                $pageInfo,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __($e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Create search criteria for product list.
     *
     * @param int $page
     * @param int $limit
     * @return SearchCriteriaInterface
     */
    private function createSearchCriteria(
        int $page,
        int $limit
    ): SearchCriteriaInterface {
        $searchCriteria = $this->searchCriteriaBuilder
            ->setCurrentPage($page)
            ->setPageSize($limit)
            ->create();

        return $searchCriteria;
    }

    /**
     * Get Product Data
     *
     * @param array $product
     * @return array
     * @throws NoSuchEntityException
     */
    public function getProductFullData($product)
    {
        $galleryUrls = [];
        $variationDetails = [];
        $categories = [];
        $productCategories = $product->getCategoryIds();

        foreach ($productCategories as $categoryId) {
            $category = $this->categoryRepository->get($categoryId);
            if ($category) {
                $categories[] = [
                    "id" => (int)$category->getId(),
                    "name" => (string)$category->getName(),
                ];
            }
        }

        // Get prices and sales
        $regularPrice = $product
            ->getPriceInfo()
            ->getPrice("regular_price")
            ->getAmount()
            ->getValue();
        $salePrice = $product
            ->getPriceInfo()
            ->getPrice("final_price")
            ->getAmount()
            ->getValue();
        $price = $salePrice ?: $regularPrice;
        $msrpPrice = $product
            ->getPriceInfo()
            ->getPrice("msrp_price")
            ->getAmount()
            ->getValue();

        $prices = [
            "regular_price" => (float)$regularPrice,
            "sale_price" => (float)$salePrice,
            "price" => (float)$price,
            "msrp_price" => (float)$msrpPrice,
            "qty" => (float)$product->getQty(),
        ];

        $variationDetails = $this->getProductVariations($product->getId());

        // Get the product images
        $galleryImages = $product->getMediaGalleryImages();

        // Get the product image
        $image = $product->getMediaGalleryImages()->getFirstItem();

        // Get the URL of the full-size image
        $imageUrl = $image->getUrl();

        // Get the URL of the thumbnail
        $thumbnailUrl = $image->getUrl("thumbnail");

        foreach ($galleryImages as $galleryImage) {
            $galleryUrls[] = [
                "url" => (string)$galleryImage->getUrl(),
                "alt" => (string)$galleryImage->getLabel(),
            ];
        }

        // Retrieve StockItemInterface for the product
        $stockItem = $this->stockRegistry->getStockItemBySku(
            $product->getSku()
        );

        return [
            "id" => (int)$product->getId(),
            "sku" => (string)$product->getSku(),
            "barcode" => $product->getCustomAttribute(
                "omniful_barcode_attribute"
            )
                ? (string)$product
                    ->getCustomAttribute("omniful_barcode_attribute")
                    ->getValue()
                : null,
            "stock_quantity" => (float)$stockItem->getQty(),
            "name" => (string)$product->getName(),
            "description" => (string)$product->getDescription(),
            "short_description" => (string)$product->getShortDescription(),
            "date_created" => (string)$product->getCreatedAt(),
            "date_modified" => (string)$product->getUpdatedAt(),
            "categories" => $categories,
            "tags" => (array)$product->getTagIds(),
            "attributes" => $this->getProductAttributes(
                $product->getId()
            ),
            "variations" => $variationDetails,
            "prices" => $prices,
            "gallery_images" => [
                "full" => (string)$imageUrl,
                "thumbnail" => (string)$thumbnailUrl,
                "images" => (array)$galleryUrls,
            ],
            "tax_class" => (int)$product->getTaxClassId(),
            "manage_stock" => (bool)$product->getManageStock(),
            "in_stock" => (bool)$stockItem->getIsInStock(),
            "backorders_allowed" => (bool)$stockItem->getBackOrder(),
            "weight" => (float)$product->getWeight(),
        ];
    }

    /**
     * Get Product Variations
     *
     * @param array $productId
     * @return array
     */
    public function getProductVariations($productId)
    {
        try {
            $product = $this->productRepository->getById($productId);
            if ($product->getTypeId() === Configurable::TYPE_CODE) {
                $variations = $this->configurableProductType->getUsedProducts(
                    $product
                );
                $variationDetails = [];

                foreach ($variations as $variation) {
                    // Get the product image
                    $image = $variation
                        ->getMediaGalleryImages()
                        ->getFirstItem();
                    $thumbnailUrl = $image->getUrl("thumbnail");

                    // Retrieve StockItemInterface for the product
                    $stockItem = $this->stockRegistry->getStockItemBySku(
                        $variation->getSku()
                    );

                    // Get variation details
                    $variationDetail = [
                        "id" => (int)$variation->getId(),
                        "sku" => (string)$variation->getSku(),
                        "barcode" => $variation->getCustomAttribute(
                            "omniful_barcode_attribute"
                        )
                            ? (string)$variation
                                ->getCustomAttribute(
                                    "omniful_barcode_attribute"
                                )
                                ->getValue()
                            : null,
                        "regular_price" => (float)$variation->getPrice(),
                        "sale_price" => (float)$variation->getSpecialPrice(),
                        "price" => (float)$variation->getFinalPrice(),
                        "stock_quantity" => (float)$stockItem->getQty(),
                        "in_stock" => (bool)$stockItem->getIsInStock(),
                        "backorders_allowed" => (bool)$stockItem->getBackOrder(),
                        "attributes" => $this->getProductAttributes(
                            $variation->getId()
                        ),
                        "thumbnail" => (string)$thumbnailUrl,
                    ];

                    // Add the variation details to the array
                    $variationDetails[] = $variationDetail;
                }
                return $variationDetails;
            }

            return [];
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __($e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Get Product Attributes With Options
     *
     * @param array $productId
     * @return array
     */
    public function getProductAttributes($productId)
    {
        try {
            $selectedAttributes = [];
            $product = $this->productRepository->getById($productId);
            $attributes = $product->getAttributes();
            foreach ($attributes as $attribute) {
                if ($product->hasData($attribute->getAttributeCode()) && $attribute->getFrontendInput() === "select") {
                    $attributeCode = $attribute->getAttributeCode();
                    $selectedOptionId = $product->getData($attributeCode);
                    $selectedOptionText = $attribute->getSource()->getOptionText($selectedOptionId);

                    $attributeData = [
                        "name" => (string)$attributeCode,
                        "label" => (string)$attribute->getDefaultFrontendLabel(),
                        "value" => $selectedOptionText,
                    ];
                    $selectedAttributes[] = $attributeData;
                }
            }
            return $selectedAttributes;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Get Product By Identifier
     *
     * @param string $identifier
     * @return mixed|string[]
     */
    public function getProductByIdentifier($identifier)
    {
        try {
            if (is_numeric($identifier)) {
                $productId = (int)$identifier;
                $product = $this->loadProductById($productId);
                $apiUrl = $this->request->getUriString();
                $storeCodeApi = $this->helper->getStoreCodeByApi($apiUrl);
                if ($storeCodeApi) {
                    $storeCode = $this->storeRepository->get($storeCodeApi);
                    $storeGroupId = [];
                    foreach ($product->getWebsiteIds() as $websiteId) {
                        $storeGroup = $this->storeManager->getWebsite($websiteId)->getGroups();
                        foreach ($storeGroup as $store) {
                            $storeGroupId[] = $store->getGroupId();
                        }
                    }
                    if (in_array($storeCode->getStoreId(), $storeGroupId)) {
                        $product = $this->productRepository
                            ->getById($product->getId(), false, $storeCode->getStoreId());
                    } else {
                        return $this->helper->getResponseStatus(
                            __("product not found."),
                            500,
                            false,
                            $data = null,
                            $pageData = null,
                            $nestedArray = true
                        );
                    }
                }
                $productData = $this->getProductFullData($product);
            } else {
                $productSku = $identifier;
                $product = $this->productRepository->get($productSku);
                $productData = $this->getProductFullData($product);
            }
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $productData,
                $pageData = null,
                $nestedArray = true
            );
        } catch (NoSuchEntityException $e) {
            return $this->helper->getResponseStatus(
                __("Product not found"),
                404,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __($e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Load product by ID
     *
     * @param int $productId
     * @return MagentoProduct
     */
    public function loadProductById($productId)
    {
        // TODO: need to load by store id
        return $this->productFactory->create()->load($productId);
    }

    /**
     * @inheritDoc
     */
    public function updateProductsInventory(
        $sku,
        int $qty,
        ?bool $status = null
    ) {
        try {
            $product = $this->productRepository->get($sku);
            $stockData = ["qty" => $qty];
            if (isset($status) && $status === "out_of_stock") {
                $stockData["is_in_stock"] = false;
            } else {
                $stockData["is_in_stock"] = true;
            }
            $apiUrl = $this->request->getUriString();
            $storeCodeApi = $this->helper->getStoreCodeByApi($apiUrl);
            if ($storeCodeApi) {
                $storeCode = $this->storeRepository->get($storeCodeApi);
                $storeGroupId = [];
                foreach ($product->getWebsiteIds() as $websiteId) {
                    $storeGroup = $this->storeManager->getWebsite($websiteId)->getGroups();
                    foreach ($storeGroup as $store) {
                        $storeGroupId[] = $store->getGroupId();
                    }
                }
                if (in_array($storeCode->getStoreId(), $storeGroupId)) {
                    $product = $this->productRepository
                        ->getById($product->getId(), false, $storeCode->getStoreId());
                } else {
                    return $this->helper->getResponseStatus(
                        __("product not found."),
                        500,
                        false,
                        $data = null,
                        $pageData = null,
                        $nestedArray = true
                    );
                }
            }
            $product->setStockData($stockData);
            $this->productRepository->save($product);
            $productData = $this->getProductData($product);
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $productData,
                $pageData = null,
                $nestedArray = true
            );
        } catch (NoSuchEntityException $e) {
            return $this->helper->getResponseStatus(
                __("Product not found"),
                404,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __($e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Get Product Data
     *
     * @param array $product
     * @return array
     * @throws NoSuchEntityException
     */
    public function getProductData($product)
    {
        $galleryUrls = [];
        $variationDetails = [];
        $categories = [];
        $productCategories = $product->getCategoryIds();

        foreach ($productCategories as $categoryId) {
            $category = $this->categoryRepository->get($categoryId);
            if ($category) {
                $categories[] = [
                    "id" => (int)$category->getId(),
                    "name" => (string)$category->getName(),
                ];
            }
        }

        // Get prices and sales
        $regularPrice = $product
            ->getPriceInfo()
            ->getPrice("regular_price")
            ->getAmount()
            ->getValue();
        $salePrice = $product
            ->getPriceInfo()
            ->getPrice("final_price")
            ->getAmount()
            ->getValue();
        $price = $salePrice ?: $regularPrice;
        $msrpPrice = $product
            ->getPriceInfo()
            ->getPrice("msrp_price")
            ->getAmount()
            ->getValue();

        $prices = [
            "regular_price" => (float)$regularPrice,
            "sale_price" => (float)$salePrice,
            "price" => (float)$price,
            "msrp_price" => (float)$msrpPrice,
            "qty" => (float)$product->getQty(),
        ];

        $variationDetails = $this->getProductVariations($product->getId());

        // Get the product images
        $galleryImages = $product->getMediaGalleryImages();

        // Get the product image
        $image = $product->getMediaGalleryImages()->getFirstItem();

        // Get the URL of the full-size image
        $imageUrl = $image->getUrl();

        // Get the URL of the thumbnail
        $thumbnailUrl = $image->getUrl("thumbnail");

        foreach ($galleryImages as $galleryImage) {
            $galleryUrls[] = [
                "url" => (string)$galleryImage->getUrl(),
                "alt" => (string)$galleryImage->getLabel(),
            ];
        }

        // Retrieve StockItemInterface for the product
        $stockItem = $this->stockRegistry->getStockItemBySku(
            $product->getSku()
        );

        return [
            "id" => (int)$product->getId(),
            "sku" => (string)$product->getSku(),
            "barcode" => $product->getCustomAttribute(
                "omniful_barcode_attribute"
            )
                ? (string)$product
                    ->getCustomAttribute("omniful_barcode_attribute")
                    ->getValue()
                : null,
            "stock_quantity" => (float)$stockItem->getQty(),
            "name" => (string)$product->getName(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function updateProductsInventorySource(
        $sku,
        int $qty,
        $sourceCode,
        $status
    ) {
        try {
            $product = $this->productRepository->get($sku);
            if (isset($status) && $status === "out_of_stock") {
                $stockData = false;
            } else {
                $stockData = true;
            }
            $this->sourceItem->setSku($sku);
            $this->sourceItem->setSourceCode($sourceCode);
            $this->sourceItem->setQuantity($qty);
            $this->sourceItem->setStatus($stockData);
            $this->sourceItemsSave->execute([$this->sourceItem]);
            $productData = $this->getProductData($product);
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $productData,
                $pageData = null,
                $nestedArray = true
            );
        } catch (NoSuchEntityException $e) {
            return $this->helper->getResponseStatus(
                __("CategoProductry not found"),
                404,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __($e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Update Bulk Products Inventory
     *
     * @param array $products
     * @return mixed|string[]
     */
    public function updateBulkProductsInventory($products)
    {
        try {
            foreach ($products as $productData) {
                $product = $this->productRepository->get($productData["sku"]);
                $apiUrl = $this->request->getUriString();
                $storeCodeApi = $this->helper->getStoreCodeByApi($apiUrl);
                if ($storeCodeApi) {
                    $storeCode = $this->storeRepository->get($storeCodeApi);
                    $storeGroupId = [];
                    foreach ($product->getWebsiteIds() as $websiteId) {
                        $storeGroup = $this->storeManager->getWebsite($websiteId)->getGroups();
                        foreach ($storeGroup as $store) {
                            $storeGroupId[] = $store->getGroupId();
                        }
                    }
                    if (in_array($storeCode->getStoreId(), $storeGroupId)) {
                        $product = $this->productRepository
                            ->getById($product->getId(), false, $storeCode->getStoreId());
                    } else {
                        return $this->helper->getResponseStatus(
                            __("product not found."),
                            500,
                            false,
                            $data = null,
                            $pageData = null,
                            $nestedArray = true
                        );
                    }
                }
                $stockData = ["qty" => $productData["qty"]];

                if (isset($productData["status"])
                    && $productData["status"] === "out_of_stock"
                ) {
                    $stockData["is_in_stock"] = false;
                } else {
                    $stockData["is_in_stock"] = true;
                }
                $product->setStockData($stockData);
                $this->productRepository->save($product);
            }
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (NoSuchEntityException $e) {
            return $this->helper->getResponseStatus(
                __("Product not found"),
                404,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __($e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * UpdateBulkProductsInventorySource
     *
     * @param array $products
     * @return mixed
     */
    public function updateBulkProductsInventorySource($products)
    {
        try {
            foreach ($products as $productData) {
                if (isset($productData["status"])
                    && $productData["status"] === "out_of_stock"
                ) {
                    $stockData = false;
                } else {
                    $stockData = true;
                }
                $this->sourceItem->setSku($productData["sku"]);
                $this->sourceItem->setSourceCode($productData["sourceCode"]);
                $this->sourceItem->setQuantity($productData["qty"]);
                $this->sourceItem->setStatus($stockData);
                $this->sourceItemsSave->execute([$this->sourceItem]);
            }
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (NoSuchEntityException $e) {
            return $this->helper->getResponseStatus(
                __("Product not found"),
                404,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __($e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Get Attribute Options
     *
     * @param AttributeInterface $attribute
     * @return array
     */
    protected function getAttributeOptions(AttributeInterface $attribute)
    {
        $options = [];

        if ($attribute->usesSource()) {
            $attributeOptions = $attribute->getSource()->getAllOptions();
            foreach ($attributeOptions as $option) {
                $options[] = $option["label"];
            }
        }

        return $options;
    }

    /**
     * Get Enable Product Data
     *
     * @param mixed $products
     * @return array
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getEnableProductData($products)
    {
        $productData = [];
        foreach ($products as $product) {
            if ($product->getStatus() == ProductStatus::STATUS_ENABLED) {
                $apiUrl = $this->request->getUriString();
                $storeCodeApi = $this->helper->getStoreCodeByApi($apiUrl);
                if ($storeCodeApi) {
                    $storeCode = $this->storeRepository->get($storeCodeApi);
                    $storeGroupId = [];
                    foreach ($product->getWebsiteIds() as $websiteId) {
                        $storeGroup = $this->storeManager->getWebsite($websiteId)->getGroups();
                        foreach ($storeGroup as $store) {
                            $storeGroupId[] = $store->getGroupId();
                        }
                    }
                    if (in_array($storeCode->getStoreId(), $storeGroupId)) {
                        $product = $this->productRepository
                            ->getById($product->getId(), false, $storeCode->getStoreId());
                        $productData[] = $this->getProductFullData($product);
                    }
                } else {
                    $productData[] = $this->getProductFullData($product);
                }
            }
        }
        return $productData;
    }

    public function getProductInfo($product) {
        return [
            "id" => (int)$product->getId(),
            "sku" => (string)$product->getSku(),
        ];
    }
}
