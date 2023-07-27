<?php

namespace Omniful\Core\Model\Stock;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\Data\StockSourceInterface;
use Omniful\Core\Api\Stock\StockSourcesInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Omniful\Core\Helper\Data;

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
     * @var Data
     */
    private $helper;

    /**
     * StockSources constructor.
     *
     * @param SourceRepositoryInterface $sourceRepository
     * @param Data $helper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        SourceRepositoryInterface $sourceRepository,
        Data $helper,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sourceRepository = $sourceRepository;
        $this->helper = $helper;
    }

    /**
     * Get stock sources.
     *
     * @return string[]
     */
    public function getStockSources()
    {
        try {
            $stockSources = $this->getStockSourcesData();

            return $this->helper->getResponseStatus(
                "Success",
                200,
                true,
                $stockSources,
            );
        } catch (\Exception $e) {
            return $this->helper->getResponseStatus(
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
     */
    public function getStockSourcesData()
    {
        $returnData = [];
        // Build the search criteria to fetch all stock sources
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $allSources = $this->sourceRepository->getList($searchCriteria);
        /** @var StockSourceInterface $source */

        foreach ($allSources->getItems() as $source) {
            $stockSources["enabled"] = (bool) $source->getEnabled();
            $stockSources["name"] = $source->getName();
            $stockSources["source_code"] = $source->getSourceCode();
            $stockSources["description"] = $source->getDescription();
            $stockSources["carrier_links"] = $source->getCarrierLinks();
            $stockSources["use_default_carrier_config"] = (bool) $source->getUseDefaultCarrierConfig();
            $stockSources["is_pickup_location_active"] = (bool) $source->getIs_pickupLocationActive();

            $stockAddressDetails["latitude"] = $source->getLatitude();
            $stockAddressDetails["longitude"] = $source->getLongitude();
            $stockAddressDetails["country_id"] = $source->getCountryId();
            $stockAddressDetails["region_id"] = (int) $source->getRegionId();
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
            $returnData[] = $stockSources;
        }

        return $returnData;
    }
}