<?php

namespace Omniful\Core\Api\Sales;

/**
 * OrderManagementInterface for third party modules
 */
interface RefundInterface
{
    /**
     * Update order status.
     *
     * @param  int $id
     * @param  mixed $items
     * @return string[]
     */
    public function processRefund(int $id, mixed $items);
}
