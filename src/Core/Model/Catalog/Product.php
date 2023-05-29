<?php

namespace Omniful\Core\Model\Catalog;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ProductMetadataInterface;

use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Omniful\Core\Api\Catalog\ProductInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Request\Http;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Model\Product as MagentoProduct;

use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;

class Product implements ProductInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var ProductInterfaceFactory
     */
    private $productFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Http
     */
    protected $request;

    protected $stockRegistry;
    protected $attributeRepository;
    protected $categoryRepository;
    protected $configurableProductType;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductMetadataInterface $productMetadata
     * @param ProductInterfaceFactory $productFactory
     */
    public function __construct(
        Http $request,
        StockRegistryInterface $stockRegistry,
        Configurable $configurableProductType,
        AttributeRepositoryInterface $attributeRepository,
        CategoryRepositoryInterface $categoryRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ProductRepositoryInterface $productRepository,
        ProductMetadataInterface $productMetadata,
        ProductInterfaceFactory $productFactory
    ) {
        $this->stockRegistry = $stockRegistry;
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productRepository = $productRepository;
        $this->productMetadata = $productMetadata;
        $this->productFactory = $productFactory;
        $this->configurableProductType = $configurableProductType;
        $this->categoryRepository = $categoryRepository;
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function getProducts()
    {
        try {
            $page = (int) $this->request->getParam("page") ?: 1;
            $limit = (int) $this->request->getParam("limit") ?: 200;

            $searchCriteria = $this->createSearchCriteria($page, $limit);
            $searchResults = $this->productRepository->getList($searchCriteria);
            $products = $searchResults->getItems();
            $totalProducts = $searchResults->getTotalCount(); // Total products count

            $productData = [];

            foreach ($products as $product) {
                $productData[] = $this->getProductData($product);
            }

            $pageInfo = [
                "current_page" => $page,
                "per_page" => $limit,
                "total_count" => $totalProducts,
                "total_pages" => ceil($totalProducts / $limit),
            ];

            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => "Success",
                "data" => $productData,
                "page_info" => $pageInfo,
            ];

            return $responseData;
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => $e->getMessage(),
            ];

            return $responseData;
        }
    }

    /**
     * Create search criteria for product list.
     *
     * @param int $page
     * @param int $limit
     * @return \Magento\Framework\Api\SearchCriteriaInterface
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
     * @inheritDoc
     */
    public function getProductByIdentifier($identifier)
    {
        try {
            if (is_numeric($identifier)) {
                $productId = (int) $identifier;
                $product = $this->loadProductById($productId);
                $productData = $this->getProductData($product);
            } else {
                $productSku = $identifier;
                $product = $this->productRepository->get($productSku);
                $productData = $this->getProductData($product);
            }

            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => "Success",
                "data" => $productData,
            ];

            return $responseData;
        } catch (NoSuchEntityException $e) {
            $responseData[] = [
                "httpCode" => 404,
                "status" => false,
                "message" => "Product not found",
            ];

            return $responseData;
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => $e->getMessage(),
            ];

            return $responseData;
        }
    }

    /**
     * @inheritDoc
     */
    public function updateProductsInventory(
        mixed $sku,
        int $qty,
        bool $status = null
    ) {
        try {
            $product = $this->productRepository->get($sku);
            $stockData = ["qty" => $qty];

            if (isset($status) && $status === "out_of_stock") {
                $stockData["is_in_stock"] = false;
            } else {
                $stockData["is_in_stock"] = true;
            }

            $product->setStockData($stockData);
            $this->productRepository->save($product);

            $productData = $this->getProductData($product);
            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => "Success",
                "data" => $productData,
            ];

            return $responseData;
        } catch (NoSuchEntityException $e) {
            $responseData[] = [
                "httpCode" => 404,
                "status" => false,
                "message" => "Product not found",
            ];

            return $responseData;
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => $e->getMessage(),
            ];

            return $responseData;
        }
    }

    /**
     * @inheritDoc
     */
    public function updateBulkProductsInventory($products)
    {
        try {
            foreach ($products as $productData) {
                $product = $this->productRepository->get($productData["sku"]);
                $stockData = ["qty" => $productData["qty"]];

                if (
                    isset($productData["status"]) &&
                    $productData["status"] === "out_of_stock"
                ) {
                    $stockData["is_in_stock"] = false;
                } else {
                    $stockData["is_in_stock"] = true;
                }

                $product->setStockData($stockData);
                $this->productRepository->save($product);
            }

            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => "Success",
            ];

            return $responseData;
        } catch (NoSuchEntityException $e) {
            $responseData[] = [
                "httpCode" => 404,
                "status" => false,
                "message" => "Product not found",
            ];

            return $responseData;
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => $e->getMessage(),
            ];

            return $responseData;
        }
    }

    /**
     * @inheritDoc
     */
    public function getProductData($product)
    {
        $galleryUrls = [];
        $variationDetails = [];

        try {
        } catch (NoSuchEntityException $e) {
            throw new \Exception("Product not found.");
        }

        $categories = [];
        $productCategories = $product->getCategoryIds();

        foreach ($productCategories as $categoryId) {
            $category = $this->categoryRepository->get($categoryId);
            if ($category) {
                $categories[] = [
                    "id" => (int) $category->getId(),
                    "name" => (string) $category->getName(),
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
        $prices = [
            "regular_price" => (float) $regularPrice,
            "sale_price" => (float) $salePrice,
            "price" => (float) $price,
            "qty" => (float) $product->getQty(),
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
            $gallery_alt = pathinfo($galleryImage->getUrl(), PATHINFO_FILENAME);

            $galleryUrls[] = [
                "url" => (string) $galleryImage->getUrl(),
                "alt" => (string) $gallery_alt,
            ];
        }

        // Retrieve StockItemInterface for the product
        $stockItem = $this->stockRegistry->getStockItemBySku(
            $product->getSku()
        );

        $productData = [
            "id" => (int) $product->getId(),
            "sku" => (string) $product->getSku(),
            "barcode" => $product->getCustomAttribute(
                "omniful_barcode_attribute"
            )
                ? (string) $product
                    ->getCustomAttribute("omniful_barcode_attribute")
                    ->getValue()
                : null,
            "stock_quantity" => (float) $stockItem->getQty(),
            "name" => (string) $product->getName(),
            "description" => (string) $product->getDescription(),
            "short_description" => (string) $product->getShortDescription(),
            "date_created" => (string) $product->getCreatedAt(),
            "date_modified" => (string) $product->getUpdatedAt(),
            "categories" => $categories,
            "tags" => (array) $product->getTagIds(),
            "attributes" => $this->getProductAttributesWithOptions(
                $product->getId()
            ),
            "variations" => $variationDetails,
            "prices" => $prices,
            "gallery_images" => [
                "full" => (string) $imageUrl,
                "thumbnail" => (string) $thumbnailUrl,
                "images" => (array) $galleryUrls,
            ],
            "tax_class" => (int) $product->getTaxClassId(),
            "manage_stock" => (bool) $product->getManageStock(),
            "in_stock" => (bool) $stockItem->getIsInStock(),
            "backorders_allowed" => (bool) $stockItem->getBackOrder(),
            "weight" => (float) $product->getWeight(),
        ];

        return $productData;
    }

    public function getProductAttributesWithOptions($productId)
    {
        try {
            $productAttributes = [];

            $product = $this->attributeRepository->get(
                MagentoProduct::ENTITY,
                $productId
            );
            $attributes = $product->getAttributes();

            foreach ($attributes as $attribute) {
                if ($attribute->getFrontendInput() === "select") {
                    $attributeData = [
                        "name" => (string) $attribute->getAttributeCode(),
                        "label" => (string) $attribute->getDefaultFrontendLabel(),
                        "options" => $this->getAttributeOptions($attribute),
                    ];
                    $productAttributes[] = $attributeData;
                }
            }

            return $productAttributes;
        } catch (\Exception $e) {
            // Handle the exception
        }
    }

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
     * Get child attribute details
     *
     * @param array $attributesData
     * @return array
     */
    public function getChildAttributes($attributesData)
    {
        $attributesDetails = [];

        foreach ($attributesData as $attribute) {
            $attributeOptions = $attribute->getOptions();

            $options = [];
            foreach ($attributeOptions as $option) {
                $options[] = $option->getLabel();
            }

            $attributeDetail = [
                "name" => (string) $attribute->getLabel(),
                "options" => $options,
            ];

            $attributesDetails[] = $attributeDetail;
        }

        return $attributesDetails;
    }

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
                        "id" => (int) $variation->getId(),
                        "sku" => (string) $variation->getSku(),
                        "barcode" => $variation->getCustomAttribute(
                            "omniful_barcode_attribute"
                        )
                            ? (string) $variation
                                ->getCustomAttribute(
                                    "omniful_barcode_attribute"
                                )
                                ->getValue()
                            : null,
                        "regular_price" => (float) $variation->getPrice(),
                        "sale_price" => (float) $variation->getSpecialPrice(),
                        "price" => (float) $variation->getFinalPrice(),
                        "stock_quantity" => (float) $stockItem->getQty(),
                        "in_stock" => (bool) $stockItem->getIsInStock(),
                        "backorders_allowed" => (bool) $stockItem->getBackOrder(),
                        "attributes" => $this->getProductAttributesWithOptions(
                            $variation->getId()
                        ),
                        "thumbnail" => (string) $thumbnailUrl,
                    ];

                    // Add the variation details to the array
                    $variationDetails[] = $variationDetail;
                }

                return $variationDetails;
            }

            return [];
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => $e->getMessage(),
            ];

            return $responseData;
        }
    }

    /**
     * Load product by ID
     * @param int $productId
     * @return \Magento\Catalog\Model\Product
     */
    public function loadProductById($productId)
    {
        $product = $this->productFactory->create()->load($productId);
        return $product;
    }
}
