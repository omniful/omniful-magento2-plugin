<?php

namespace Omniful\Core\Api\Sales;

/**
 * OrderInterface for third party modules
 */
interface OrderInterface
{
    /**
     * Get orders.
     *
     * @return string[]
     */
    public function getOrders();

    /**
     * Get order by ID.
     *
     * @param int $id
     * @return string[]
     */
    public function getOrderById(int $id);
}
