<?php

namespace Omniful\Core\Api\Catalog;

/**
 * CategoryInterface for third party modules
 */
interface CategoryInterface
{
    /**
     * Get orders.
     *
     * @return string[]
     */
    public function getCategories();

    /**
     * Get category by ID.
     *
     * @param int $id
     * @return string[]
     */
    public function getCategoryById(int $id);
}
