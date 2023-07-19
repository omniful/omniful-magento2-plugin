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
     * @param $sku
     * @return string[]
     */
    public function getProductBySku($sku);

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
     * @param $products
     * @return string[]
     */
    public function updateBulkProductsInventory($products);

    /**
     * Update Product Inventory
     *
     * @param $sku
     * @param int $qty
     * @param bool $status
     * @return string[]
     */
    public function updateProductsInventory(
        $sku,
        int $qty,
        bool $status = null
    );
}