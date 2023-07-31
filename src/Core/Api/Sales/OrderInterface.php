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
     * @return string[] Array of orders.
     * @return string[]
     */
    public function getOrders();

    /**
     * Get order by ID.
     *
     * @param int $id The ID of the order.
     * @return string[] Order data.
     * @param  int $id
     * @return string[]
     */
    public function getOrderById(int $id);
}

