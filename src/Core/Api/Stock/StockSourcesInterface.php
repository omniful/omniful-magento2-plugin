<?php

namespace Omniful\Core\Api\Stock;

/**
 * StockSourcesInterface for third-party modules
 */
interface StockSourcesInterface
{
    /**
     * Get stock sources.
     *
     * @return string[] An array of stock sources.
     */
    public function getStockSources(): array;
}