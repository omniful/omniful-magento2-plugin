<?php

namespace Omniful\Core\Model\Stock;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\Data\StockSourceInterface;
use Omniful\Core\Api\Stock\StockSourcesInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

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
     * StockSources constructor.
     *
     * @param SourceRepositoryInterface $sourceRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        SourceRepositoryInterface $sourceRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sourceRepository = $sourceRepository;
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
//            $allSources = $this->stockSourceRepository->getList($searchCriteria);
            $allSources = $this->sourceRepository->getList($searchCriteria);
            /** @var StockSourceInterface $source */
            foreach ($allSources->getItems() as $source) {
                $stockSources[] = $source->getSourceCode();
            }
        } catch (\Exception $e) {
            return $e->getMessage();
            // Handle exceptions here, if needed
        }
        return $stockSources;
    }
}
