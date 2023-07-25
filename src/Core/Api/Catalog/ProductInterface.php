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
     * @param  string $identifier
     * @return string[]
     */
    public function getProductByIdentifier($identifier);

    /**
     * Update product in Bulk.
     *
     * @param  string $products
     * @return string[]
     */
    public function updateBulkProductsInventory($products);

    /**
     * Update product by SKU
     *
     * @param  string    $sku
     * @param  int       $qty
     * @param  bool|null $status
     * @return mixed
     */
    public function updateProductsInventory(
        $sku,
        int $qty,
        bool $status = null
    );
}
