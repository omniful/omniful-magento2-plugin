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
     * @param  string $sku
     * @return string[]
     */
    public function getProductBySku($sku);

    /**
     * Get product by id
     *
     * @param  int $id
     * @return string[]
     */
    public function getProductById(int $id);

    /**
     * Update Bulk Products Inventory
     *
     * @param mixed $products
     * @return mixed
     */
    public function updateBulkProductsInventory($products);

    /**
     * Update Product Inventory
     *
     * @param  string $sku
     * @param  int    $qty
     * @param  bool   $status
     * @return string[]
     */
    public function updateProductsInventory(
        $sku,
        int $qty,
        bool $status = null
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
        $sku,
        int $qty,
        $sourceCode,
        $status
    );

    /**
     * Update Bulk Products Inventory
     *
     * @param mixed $products
     * @return mixed
     */
    public function updateBulkProductsInventorySource($products);
}
