<?php

namespace Omniful\Core\Model\Stock;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\StockSourceInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventorySales\Model\ResourceModel\GetAssignedStockIdForWebsite;
use Magento\Store\Model\ResourceModel\Website\CollectionFactory as WebsiteCollectionFactory;
use Omniful\Core\Api\Stock\StockSourcesInterface;
use Omniful\Core\Helper\Data as CoreHelper;

class StockSources implements StockSourcesInterface
{
    /**
     * @var SearchCriteriaBuilder
     */
    public $searchCriteriaBuilder;

    /**
     * @var SourceRepositoryInterface
     */
    public $sourceRepository;

    /**
     * @var CoreHelper
     */
    public $coreHelper;

    /**
     * @var WebsiteCollectionFactory
     */
    public $websiteCollectionFactory;

    /**
     * @var GetStockSourceLinksInterface
     */
    public $getStockSourceLinks;

    /**
     * @var GetAssignedStockIdForWebsite
     */
    public $getAssignedStockIdForWebsite;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    public $searchCriteriaBuilderFactory;
    /**
     * @var StockRepositoryInterface
     */
    public $stockRepository;

    /**
     * StockSources constructor.
     *
     * @param CoreHelper $coreHelper
     * @param SourceRepositoryInterface $sourceRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param GetStockSourceLinksInterface $getStockSourceLinks
     * @param StockRepositoryInterface $stockRepository
     * @param WebsiteCollectionFactory $websiteCollectionFactory
     * @param GetAssignedStockIdForWebsite $getAssignedStockIdForWebsite
     */
    public function __construct(
        CoreHelper $coreHelper,
        SourceRepositoryInterface $sourceRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        GetStockSourceLinksInterface $getStockSourceLinks,
        StockRepositoryInterface $stockRepository,
        WebsiteCollectionFactory $websiteCollectionFactory,
        GetAssignedStockIdForWebsite $getAssignedStockIdForWebsite
    ) {
        $this->coreHelper = $coreHelper;
        $this->sourceRepository = $sourceRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->websiteCollectionFactory = $websiteCollectionFactory;
        $this->getStockSourceLinks = $getStockSourceLinks;
        $this->stockRepository = $stockRepository;
        $this->getAssignedStockIdForWebsite = $getAssignedStockIdForWebsite;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
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
                __("Success"),
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
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $stockInfo = null;
        $stockData = $this->stockRepository->getList($searchCriteria);
        $sourceData = [];
        $sourceWebsiteData = $this->getSourceWebsiteData($stockData);

        $returnData = [];
        foreach ($stockData->getItems() as $stock) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(StockSourceLinkInterface::STOCK_ID, $stock->getStockId())
                ->create();

            foreach ($this->getStockSourceLinks->execute($searchCriteria)->getItems() as $source) {
                $sourceData[] = $this->sourceRepository->get($source->getSourceCode());
                $returnData[] = $this->getData(
                    $sourceData,
                    $sourceWebsiteData
                );
            }
        }
        return $returnData;
    }

    /**
     * Get Source Website Data
     *
     * @param array $stockData
     * @return array
     */
    public function getSourceWebsiteData($stockData)
    {
        $sourceWebsiteData = [];
        foreach ($stockData->getItems() as $stock) {
            foreach ($stock->getExtensionAttributes()->getSalesChannels() as $salesChannel) {
                $websiteCode = $salesChannel->getCode();
                $stockId = $this->getAssignedStockIdForWebsite->execute(
                    $websiteCode
                );
                if ($stockId == $stock->getStockId()) {
                    $searchCriteria = $this->searchCriteriaBuilder
                        ->addFilter(StockSourceLinkInterface::STOCK_ID, $stock->getStockId())
                        ->create();

                    foreach ($this->getStockSourceLinks->execute($searchCriteria)->getItems() as $source) {
                        $sourceWebsiteData[$source->getSourceCode()][] = $websiteCode;
                    }
                }
            }
        }
        return $sourceWebsiteData;
    }

    /**
     * Get Data
     *
     * @param mixed $sourceItems
     * @param array $sourceWebsiteData
     * @return mixed
     */
    public function getData($sourceItems, $sourceWebsiteData = [])
    {
        $stockSources = [];
        foreach ($sourceItems as $key => $source) {
            $stockSources["enabled"] = (bool)$source->getEnabled();
            $stockSources["name"] = $source->getName();
            $stockSources["source_code"] = $source->getSourceCode();
            $stockSources["website_codes"] = $sourceWebsiteData[$source->getSourceCode()] ?? [];
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
