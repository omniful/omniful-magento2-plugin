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
     * Get stock sources.
     *
     * @return string[]
     */
    public function getStockSourcesData()
    {
        $stockSources = [];
        // Build the search criteria to fetch all stock sources
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $allSources = $this->sourceRepository->getList($searchCriteria);
        /** @var StockSourceInterface $source */
        foreach ($allSources->getItems() as $source) {
            $stockSources[] = $source->getSourceCode();
        }
        return $stockSources;
    }
}
