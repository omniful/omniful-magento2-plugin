<?php

namespace Omniful\Core\Model\Store;

use Exception;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Api\Stock\StockSourcesInterface;
use Omniful\Core\Api\Store\InfoInterface;
use Omniful\Core\Helper\Data as CoreHelper;

class Info implements InfoInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CollectionFactory
     */
    protected $statusCollectionFactory;

    /**
     * @var CoreHelper
     */
    protected $coreHelper;

    /**
     * @var stockSourcesInterface
     */
    private $stockSourcesInterface;

    /**
     * Info constructor.
     *
     * @param CoreHelper $coreHelper
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $statusCollectionFactory
     * @param StockSourcesInterface $stockSourcesInterface
     */
    public function __construct(
        CoreHelper $coreHelper,
        StoreManagerInterface $storeManager,
        CollectionFactory $statusCollectionFactory,
        StockSourcesInterface $stockSourcesInterface
    ) {
        $this->coreHelper = $coreHelper;
        $this->storeManager = $storeManager;
        $this->stockSourcesInterface = $stockSourcesInterface;
        $this->statusCollectionFactory = $statusCollectionFactory;
    }

    /**
     * Retrieve admin user and store information
     *
     * @return array
     */
    public function getStoreInfo(): array
    {
        try {
            // Retrieve all store data
            $allStores = $this->getAllStoresInfo();

            // Retrieve all store info
            $storeDetails = $this->getStoreDetails();

            // Retrieve all order statuses
            $orderStatuses = $this->getOrderStatuses();

            // Retrieve all stock sources
            $stockSources = $this->stockSourcesInterface->getStockSourcesData();

            return [
                "data" => [
                    "store_info" => $storeDetails,
                    "all_stores" => $allStores,
                    "stock_sources" => $stockSources,
                    "order_statuses" => $orderStatuses,
                    "open_source_version" => "v2.2.0",
                    "adobe_commerce_version" => "vCommerce1.0.19"
                ],
            ];
        } catch (Exception $e) {
            return [
                "data" => null,
                "error" => [
                    "code" => 500,
                    "message" => (string)$e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Retrieve all store info
     *
     * @return array
     */
    private function getAllStoresInfo(): array
    {
        $websites = $this->storeManager->getWebsites();
        $storeGroup = $this->storeManager->getGroups();
        $allStores = [];

        // Organize websites and their related stores
        foreach ($websites as $website) {
            $websiteData = [
                "website_id" => (int)$website->getId(),
                "website_code" => (string)$website->getCode(),
                "website_name" => (string)$website->getName(),
                "stores" => [], // Initialize an empty array to store related stores
            ];

            foreach ($storeGroup as $store) {
                if ($store->getWebsiteId() === $website->getId()) {
                    $storeData = [
                        "store_id" => (int)$store->getId(),
                        "store_name" => (string)$store->getName(),
                        "store_code" => (string)$store->getCode(),
                        "store_group_id" => (int)$store->getGroupId(),
                        "store_group_name" => (string)$store->getName(),
                        "store_views" => [], // Initialize an empty array to store related store views
                    ];
                    foreach ($store->getStores() as $storeView) {
                        $storeViewData = [
                            "store_view_id" => (int)$storeView->getId(),
                            "store_view_name" => (string)$storeView->getName(),
                            "store_view_code" => (string)$storeView->getCode(),
                        ];
                        $storeData["store_views"][] = $storeViewData;
                    }
                    $websiteData["stores"][] = $storeData;
                }
            }

            $allStores["websites"][] = $websiteData;
        }
        return $allStores;
    }

    /**
     * Retrieve all store data
     *
     * @return array
     */
    private function getStoreDetails(): array
    {
        $storeDetails = [];
        // General Store Information
        $storeDetails["general"] = [
            "store_name" => $this->coreHelper->getConfigValue(
                "general/store_information/name"
            ),
            "store_email" => $this->coreHelper->getConfigValue(
                "trans_email/ident_general/email"
            ),
            "store_phone" => $this->coreHelper->getConfigValue(
                "general/store_information/phone"
            ),
            "store_currency_code" => $this->coreHelper->getConfigValue(
                "currency/options/base"
            ),
            "store_country" => $this->coreHelper->getConfigValue(
                "general/store_information/country_id"
            ),
            "store_timezone" => $this->coreHelper->getConfigValue(
                "general/locale/timezone"
            ),
            "store_locale" => $this->coreHelper->getConfigValue(
                "general/locale/code"
            ),
        ];

        // Sales-related Settings
        $storeDetails["sales"] = [
            "default_payment_method" => $this->coreHelper->getConfigValue(
                "payment/default"
            ),
            "default_shipping_method" => $this->coreHelper->getConfigValue(
                "shipping/origin/shipping_method"
            ),
            "allowed_countries" => $this->coreHelper->getAllowedCountries(),
        ];

        // Catalog-related Settings
        $storeDetails["catalog"] = [
            "root_category" => (int)$this->coreHelper->getConfigValue(
                "catalog/category/root_id"
            ),
            "default_category" => (int)$this->coreHelper->getConfigValue(
                "catalog/category/root_id"
            ),
        ];
        return $storeDetails;
    }

    /**
     * Retrieve all order statuses
     *
     * @return array
     */
    private function getOrderStatuses(): array
    {
        $orderStatuses = [];
        $statusCollection = $this->statusCollectionFactory->create();
        $statuses = $statusCollection->toOptionArray();
        foreach ($statuses as $status) {
            $orderStatuses[] = [
                "title" => $status["label"],
                "code" => $status["value"],
            ];
        }
        return $orderStatuses;
    }
}
