<?php

namespace Omniful\Core\Model\Stock;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\StockSourceInterface;
use Omniful\Core\Api\Stock\StockSourcesInterface;
use Omniful\Core\Helper\CacheManager as CacheManagerHelper;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Omniful\Core\Helper\Data as CoreHelper;
use Magento\Store\Model\ResourceModel\Website\CollectionFactory as WebsiteCollectionFactory;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;
use Magento\InventorySales\Model\ResourceModel\GetAssignedStockIdForWebsite;

class StockSources implements StockSourcesInterface
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SourceRepositoryInterface
     */
    private $sourceRepository;

    /**
     * @var CoreHelper
     */
    private $coreHelper;

    /**
     * @var WebsiteCollectionFactory
     */
    private $websiteCollectionFactory;

    /**
     * @var GetStockSourceLinksInterface
     */
    private $getStockSourceLinks;

    /**
     * @var GetAssignedStockIdForWebsite
     */
    private $getAssignedStockIdForWebsite;
    /**
     * @var CacheManagerHelper
     */
    private $cacheManagerHelper;

    /**
     * StockSources constructor.
     *
     * @param CoreHelper $coreHelper
     * @param SourceRepositoryInterface $sourceRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CacheManagerHelper $cacheManagerHelper
     * @param GetStockSourceLinksInterface $getStockSourceLinks
     * @param WebsiteCollectionFactory $websiteCollectionFactory
     * @param GetAssignedStockIdForWebsite $getAssignedStockIdForWebsite
     */
    public function __construct(
        CoreHelper $coreHelper,
        SourceRepositoryInterface $sourceRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CacheManagerHelper $cacheManagerHelper,
        GetStockSourceLinksInterface $getStockSourceLinks,
        WebsiteCollectionFactory $websiteCollectionFactory,
        GetAssignedStockIdForWebsite $getAssignedStockIdForWebsite
    ) {
        $this->coreHelper = $coreHelper;
        $this->sourceRepository = $sourceRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->websiteCollectionFactory = $websiteCollectionFactory;
        $this->getStockSourceLinks = $getStockSourceLinks;
        $this->getAssignedStockIdForWebsite = $getAssignedStockIdForWebsite;
        $this->cacheManagerHelper = $cacheManagerHelper;
    }

    /**
     * Get stock sources.
     *
     * @return string[]
     */
    public function getStockSources(): array
    {
        try {
            $stockSources = $this->getStockSourcesData();

            return $this->coreHelper->getResponseStatus(
                "Success",
                200,
                true,
                $stockSources
            );
        } catch (Exception $e) {
            return $this->coreHelper->getResponseStatus(
                __($e->getMessage()),
                500,
                false
            );
        }
    }

    /**
     * Get stock sources.
     *
     * @return string[]
     * @throws NoSuchEntityException
     */
    public function getStockSourcesData()
    {
        $storeId = $this->coreHelper->getStoreId();
        $cacheIdentifier = $this->cacheManagerHelper ::STOCK_SOURCE_CODE.$storeId;
        if ($this->cacheManagerHelper->isDataAvailableInCache($cacheIdentifier)) {
            return $this->cacheManagerHelper->getDataFromCache($cacheIdentifier);
        }
        $websiteCollection = $this->websiteCollectionFactory->create();
        $returnData = [];
        foreach ($websiteCollection as $website){
            $sourceData = [];
            $stockId = $this->getAssignedStockIdForWebsite->execute($website->getCode());

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(StockSourceLinkInterface::STOCK_ID, $stockId)
                ->create();

            foreach ($this->getStockSourceLinks->execute($searchCriteria)->getItems() as $source) {
                $sourceData[] = $this->sourceRepository->get($source->getSourceCode());
                $returnData[$website->getCode()][] = $this->getData($sourceData);
            }
        }
        if ($cacheIdentifier) {
            $this->cacheManagerHelper->saveDataToCache($cacheIdentifier, $returnData);
        }
        return $returnData;
    }


    /**
     * Get Data
     *
     * @param mixed $sourceItems
     * @return mixed
     */
    public function getData($sourceItems)
    {
        foreach ($sourceItems as $source) {
            $stockSources["enabled"] = (bool)$source->getEnabled();
            $stockSources["name"] = $source->getName();
            $stockSources["source_code"] = $source->getSourceCode();
            $stockSources["description"] = $source->getDescription();
            $stockSources["carrier_links"] = $source->getCarrierLinks();
            $stockSources["use_default_carrier_config"] = (bool)$source->getUseDefaultCarrierConfig();
            $stockSources["is_pickup_location_active"] = (bool)$source->getIs_pickupLocationActive();
            $stockAddressDetails["latitude"] = $source->getLatitude();
            $stockAddressDetails["longitude"] = $source->getLongitude();
            $stockAddressDetails["country_id"] = $source->getCountryId();
            $stockAddressDetails["region_id"] = (int)$source->getRegionId();
            $stockAddressDetails["region"] = $source->getRegion();
            $stockAddressDetails["city"] = $source->getCity();
            $stockAddressDetails["street"] = $source->getStreet();
            $stockAddressDetails["postcode"] = $source->getPostcode();
            $stockSources["address"] = $stockAddressDetails;
            $stockContactDetails["fax"] = $source->getFax();
            $stockContactDetails["email"] = $source->getEmail();
            $stockContactDetails["phone"] = $source->getPhone();
            $stockContactDetails["contact_name"] = $source->getContactName();
            $stockSources["contact_info"] = $stockContactDetails;
        }
        return $stockSources;
    }
}
