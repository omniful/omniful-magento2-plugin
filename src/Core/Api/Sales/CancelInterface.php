<?php

namespace Omniful\Core\Api\Sales;

/**
 * OrderManagementInterface for third party modules
 */
interface CancelInterface
{
    /**
     * Cancel order
     *
     * @param int    $id The ID of the order to be canceled.
     * @param string $cancel_reason The reason for canceling the order.
     * @return mixed
     */
    public function processCancel(int $id, string $cancel_reason);
}
