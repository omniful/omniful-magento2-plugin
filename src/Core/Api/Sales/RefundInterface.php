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
     * @param  int   $id
     * @param  mixed $items
     * @return mixed
     */
    public function processRefund(int $id, $items);
}
