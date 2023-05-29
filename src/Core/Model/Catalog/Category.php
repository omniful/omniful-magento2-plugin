<?php

namespace Omniful\Core\Model\Catalog;

use Magento\Framework\Exception\NoSuchEntityException;
use Omniful\Core\Api\Catalog\CategoryInterface;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollection;
use Omniful\Core\Helper\CacheManager;

class Category implements CategoryInterface
{
    const SUB_CAT_DATA_INDEXER_CACHE_ID = "sub_category_data_cache_";

    private $categoryRepository;

    protected $storeManager;

    protected $categoryCollectionFactory;

    protected $cacheManager;
    protected $categoryCollection;

    public function __construct(
        CacheManager $cacheManager,
        CategoryCollection $categoryCollection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
    ) {
        $this->cacheManager = $cacheManager;
        $this->storeManager = $storeManager;
        $this->categoryCollection = $categoryCollection;
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function getCategories()
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();
            $collection = $this->categoryCollectionFactory->create();
            $collection
                ->addAttributeToSelect("*")
                ->setStoreId($storeId)
                ->addIsActiveFilter();

            // get the default category ID for the current store
            $defaultCategoryId = $this->storeManager
                ->getStore()
                ->getRootCategoryId();

            $categories = $collection->getItems();
            $categoryData = [];

            /** @var \Magento\Catalog\Model\Category $category */
            foreach ($categories as $category) {
                $categoryId = $category->getId();
                if ($categoryId == $defaultCategoryId) {
                    continue;
                }

                $categoryData[] = $this->getCategoryData($category);
            }

            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => "Success",
                "data" => $categoryData,
            ];

            return $responseData;
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => $e->getMessage(),
            ];

            return [$responseData];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCategoryById(int $id)
    {
        try {
            $category = $this->categoryRepository->get($id);

            if ($category->getId()) {
                $responseData[] = [
                    "httpCode" => 200,
                    "status" => true,
                    "message" => "Success",
                    "data" => $this->getCategoryData($category),
                ];
            } else {
                $responseData[] = [
                    "httpCode" => 404,
                    "status" => false,
                    "message" => "Category not found",
                ];
            }
            return $responseData;
        } catch (NoSuchEntityException $e) {
            $responseData[] = [
                "httpCode" => 404,
                "status" => false,
                "message" => "Category not found",
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
     * Recursively builds the category tree structure.
     *
     * @param array $categories
     * @param int $parentId
     * @return array
     */
    private function buildCategoryTree(array $categories, $parentId = 0)
    {
        $tree = [];
        foreach ($categories as $category) {
            if ($category["parent_id"] == $parentId) {
                $categoryData = [
                    "id" => (int) $category["category_id"],
                    "name" => (string) $category["name"],
                    "children" => $this->buildCategoryTree(
                        $categories,
                        $category["category_id"]
                    ),
                ];
                $tree[] = $categoryData;
            }
        }
        return $tree;
    }

    /**
     * Retrieves category data and sets the data type for each value.
     *
     * @param array $categoryData
     * @return array
     */
    private function getCategoryData($category)
    {
        $categoryId = $category->getId();
        $storeId = $this->storeManager->getStore()->getId();

        $categoryData["id"] = (int) $category->getId();
        $categoryData["urlKey"] = (string) $category->getUrlKey();
        $categoryData["name"] = (string) $category->getName();
        $categoryData["level"] = (int) $category->getLevel();
        $categoryData["parentId"] = (int) $category->getParentId();
        $categoryData["position"] = (int) $category->getPosition();
        $categoryData["isActive"] = (bool) $category->getIsActive();
        $categoryData["createdAt"] = (string) $category->getCreatedAt();
        $categoryData["updatedAt"] = (string) $category->getUpdatedAt();
        $categoryData["childrenCount"] = (int) $category->getChildrenCount();
        $categoryData["attributeSetId"] = (int) $category->getAttributeSetId();
        $categoryData["productCount"] = (int) $category->getProductCount();
        $categoryData["hasChildren"] =
            (bool) $category->getChildrenCount() > 0 ? true : false;

        $categoryData["displayMode"] = (string) $category->getDisplayMode();
        $categoryData["includeInMenu"] = (bool) $category->getIncludeInMenu();
        $categoryData["description"] = $category->getDescription();

        // Getting Sub Categories Data /////////////////////////////////////////////////
        if ($categoryData["hasChildren"]) {
            $categoryData["subCategoriesData"] = $this->getSubCategoriesData(
                $categoryId,
                $storeId
            );
        }

        $returnData = $categoryData;

        return $returnData;
    }

    /**
     * getSubCategoriesData
     *
     * @param integer $categoryId
     * @param integer $storeId
     * @return array
     */
    public function getSubCategoriesData(int $categoryId, int $storeId): array
    {
        $returnData = [];
        $subCategoryCollection = $this->categoryCollection
            ->create()
            ->addAttributeToSelect("*")
            ->addFieldToFilter("parent_id", $categoryId);

        $cacheIdentifier = null;

        // Creating categories data //////////////////////////////////////////
        foreach ($subCategoryCollection as $subCategory) {
            $cacheIdentifier =
                self::SUB_CAT_DATA_INDEXER_CACHE_ID .
                $storeId .
                "_" .
                $subCategory->getId();
            if ($this->cacheManager->isDataAvailableInCache($cacheIdentifier)) {
                return $this->cacheManager->getDataFromCache($cacheIdentifier);
            } else {
                $subCategoryData = $this->getCategoryData($subCategory);

                $returnData[] = $subCategoryData;
            }
        }

        if ($cacheIdentifier) {
            $this->cacheManager->saveDataToCache($cacheIdentifier, $returnData);
        }

        return $returnData;
    }
}
