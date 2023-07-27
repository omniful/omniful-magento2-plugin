<?php

namespace Omniful\Core\Model\Catalog;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Api\Catalog\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollection;
use Omniful\Core\Helper\CacheManager;
use Omniful\Core\Helper\Data;

class Category implements CategoryInterface
{
    public const SUB_CAT_DATA_INDEXER_CACHE_ID = "sub_category_data_cache_";
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var CategoryCollection
     */
    protected $categoryCollectionFactory;
    /**
     * @var CacheManager
     */
    protected $cacheManager;
    /**
     * @var CategoryCollection
     */
    protected $categoryCollection;
    /**
     * @var Data
     */
    private $helper;

    /**
     * Category constructor.
     *
     * @param CacheManager $cacheManager
     * @param CategoryCollection $categoryCollection
     * @param Data $helper
     * @param StoreManagerInterface $storeManager
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CategoryCollection $categoryCollectionFactory
     */
    public function __construct(
        CacheManager $cacheManager,
        CategoryCollection $categoryCollection,
        Data $helper,
        StoreManagerInterface $storeManager,
        CategoryRepositoryInterface $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
    ) {
        $this->cacheManager = $cacheManager;
        $this->storeManager = $storeManager;
        $this->categoryCollection = $categoryCollection;
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->helper = $helper;
    }

    /**
     * Get Categories
     *
     * @return array|string[]
     */
    public function getCategories(): array
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

            /**
             * @var \Magento\Catalog\Model\Category $category
             */
            foreach ($categories as $category) {
                $categoryId = $category->getId();
                if ($categoryId == $defaultCategoryId) {
                    continue;
                }

                $categoryData[] = $this->getCategoryData($category);
            }
            return $this->helper->getResponseStatus(
                "Success",
                200,
                true,
                $categoryData,
                $pageData = null,
                $nestedArray = true
            );
        } catch (\Exception $e) {
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
     * Get Category By Id
     *
     * @param int $id
     * @return mixed|string[]
     */
    public function getCategoryById(int $id): array
    {
        try {
            $category = $this->categoryRepository->get($id);

            if ($category->getId()) {
                return $this->helper->getResponseStatus(
                    "Success",
                    200,
                    true,
                    $this->getCategoryData($category),
                    $pageData = null,
                    $nestedArray = true
                );
            } else {
                return $this->helper->getResponseStatus(
                    __(
                        "Category not found"
                    ),
                    404,
                    false,
                    $data = null,
                    $pageData = null,
                    $nestedArray = true
                );
            }
        } catch (NoSuchEntityException $e) {
            return $this->helper->getResponseStatus(
                __(
                    "Category not found"
                ),
                404,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (\Exception $e) {
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
     * Recursively builds the category tree structure.
     *
     * @param  array $categories
     * @param  int   $parentId
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
     * @param  mixed $category
     * @return mixed
     * @throws NoSuchEntityException
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
        $categoryData["hasChildren"] = (bool) $category->getChildrenCount() > 0 ? true : false;
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
        return $categoryData;
    }

    /**
     * GetSubCategoriesData
     *
     * @param integer $categoryId
     * @param integer $storeId
     * @return array
     * @throws NoSuchEntityException
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