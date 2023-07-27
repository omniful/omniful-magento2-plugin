<?php

namespace Omniful\Core\Model\Stock;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\Data\StockSourceInterface;
use Magento\InventoryApi\Api\StockSourceRepositoryInterface;
use Omniful\Core\Api\Stock\StockSourcesInterface;

class StockSources implements StockSourcesInterface
{
    /**
     * @var StockSourceRepositoryInterface
     */
    private $stockSourceRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * StockSources constructor.
     *
     * @param StockSourceRepositoryInterface $stockSourceRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        StockSourceRepositoryInterface $stockSourceRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->stockSourceRepository = $stockSourceRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Get stock sources.
     *
     * @return string[]
     */
    public function getStockSources()
    {
        $stockSources = [];

        try {
            // Build the search criteria to fetch all stock sources
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $allSources = $this->stockSourceRepository->getList($searchCriteria);

            /** @var StockSourceInterface $source */
            foreach ($allSources->getItems() as $source) {
                $stockSources[] = $source->getSourceCode();
            }
        } catch (\Exception $e) {
            // Handle exceptions here, if needed
        }

        return $stockSources;
    }
}