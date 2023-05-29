<?php

namespace Omniful\Core\Api;

/**
 * ProductManagementInterface for third party modules
 */
interface ProductManagementInterface
{
    /**
     * Get product list
     *
     * @return string[]
     */
    public function getProducts();

    /**
     * Get product by sku
     *
     * @param mixed $sku
     * @return string[]
     */
    public function getProductBySku(mixed $sku);

    /**
     * Get product by id
     *
     * @param int $id
     * @return string[]
     */
    public function getProductById(int $id);

    /**
     * Bulk Update Product Inventory
     *
     * @param int $accountId
     * @param mixed $products
     * @return string[]
     */
    public function updateBulkProductsInventory(mixed $products);

    /**
     * Update Product Inventory
     *
     * @param mixed $sku
     * @param int $qty
     * @param bool $status
     * @return string[]
     */
    public function updateProductsInventory(
        mixed $sku,
        int $qty,
        bool $status = null
    );
}
