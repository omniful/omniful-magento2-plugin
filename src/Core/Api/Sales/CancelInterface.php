<?php

namespace Omniful\Core\Api\Sales;

/**
 * OrderManagementInterface for third party modules
 */
interface CancelInterface
{
    /**
     * Cancel order.
     *
     * @param int $id
     * @param  string $cancel_reason
     * @return string[]
     */
    public function processCancel(int $id, string $cancel_reason);
}
