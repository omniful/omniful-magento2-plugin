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
     * @param mixed $identifier
     * @return string[]
     */
    public function getProductByIdentifier(mixed $identifier);

    /**
     * Update product in Bulk.
     *
     * @param mixed $products
     * @return string[]
     */
    public function updateBulkProductsInventory(mixed $products);

    /**
     * Update product by SKU.
     *
     * @param mixed $sku
     * @return string[]
     */
    public function updateProductsInventory(
        mixed $sku,
        int $qty,
        bool $status = null
    );
}
