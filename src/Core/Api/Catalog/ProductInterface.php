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
     * @return string[][] An array of products.
     */
    public function getProducts();

    /**
     * Get product by identifier.
     *
     * @param string $identifier The identifier of the product.
     * @return string[] An array containing the details of the product.
     */
    public function getProductByIdentifier(string $identifier);

    /**
     * Update product in Bulk
     *
     * @param mixed $products An array of products to update.
     * @return mixed The result of the bulk update operation.
     */
    public function updateBulkProductsInventory($products);

    /**
     * Update product by SKU
     *
     * @param string $sku The SKU of the product.
     * @param int $qty The quantity of the product to update.
     * @param bool|null $status The status of the product (optional).
     * @return mixed The result of the update operation.
     */
    public function updateProductsInventory(string $sku, int $qty, ?bool $status = null);

    /**
     * Update product by SKU and source code
     *
     * @param string $sku The SKU of the product.
     * @param int $qty The quantity of the product to update.
     * @param string $sourceCode The source code of the inventory update.
     * @param string $status The status of the product.
     * @return mixed The result of the update operation.
     */
    public function updateProductsInventorySource(string $sku, int $qty, string $sourceCode, string $status);

    /**
     * Update product in Bulk by source code
     *
     * @param mixed $products An array of products to update with source code.
     * @return mixed The result of the bulk update operation.
     */
    public function updateBulkProductsInventorySource($products);
}
