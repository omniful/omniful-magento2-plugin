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
     * Update product in Bulk
     *
     * @param mixed $products
     * @return mixed
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

        string $sku,
        int $qty,
        ?bool $status = null
    );

    /**
     * Update product by SKU
     *
     * @param string $sku
     * @param int $qty
     * @param string $sourceCode
     * @param string $status
     * @return mixed
     */
    public function updateProductsInventorySource(
        string $sku,
        int $qty,
        string $sourceCode,
        string $status
    );

    /**
     * Update product in Bulk
     *
     * @param mixed $products
     * @return mixed
     */
    public function updateBulkProductsInventorySource(array $products);
}
