<?php

namespace Omniful\Core\Api\Sales;

/**
 * OrderManagementInterface for third party modules
 */
interface RefundInterface
{
    /**
     * Update order status
     *
     * @param int   $id The ID of the order to process the refund for.
     * @param mixed $items The items to be refunded.
     * @return mixed
     */
    public function processRefund(int $id, $items);
}
