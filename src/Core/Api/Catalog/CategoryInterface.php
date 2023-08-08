<?php

namespace Omniful\Core\Api\Catalog;

/**
 * CategoryInterface for third party modules
 */
interface CategoryInterface
{
    /**
     * Get categories.
     *
     * @return string[] An array of category names.
     */
    public function getCategories();

    /**
     * Get category by ID.
     *
     * @param int $id The ID of the category to retrieve.
     * @return string[] An array containing the details of the category.
     */
    public function getCategoryById(int $id);
}
