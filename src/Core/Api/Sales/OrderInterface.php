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
     * Loads a specified order.
     *
     * @param int $id The order ID.
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    public function getOrderById(int $id);
}
