<?php

namespace Omniful\Core\Api\Catalog;

/**
 * ProductInterface for third party modules
 */
interface ProductInterface
{
    /**
     * Get products.
     *
     * @return string[][]
     */
    public function getProducts();

    /**
     * Get product by identifier.
     *
     * @param $identifier
     * @return string[]
     */
    public function getProductByIdentifier($identifier);

    /**
     * Update product in Bulk.
     *
     * @param $products
     * @return string[]
     */
    public function updateBulkProductsInventory($products);

    /**
     * Update product by SKU.
     *
     * @param $sku
     * @return string[]
     */
    public function updateProductsInventory(
        $sku,
        int $qty,
        bool $status = null
    );
}