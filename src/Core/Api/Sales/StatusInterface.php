<?php

namespace Omniful\Core\Api\Sales;

/**
 * StatusInterface for third party modules
 */
interface StatusInterface
{
    /**
     * Update order status
     *
     * @param int         $id The ID of the order to update the status for.
     * @param string      $status The new status for the order.
     * @param mixed       $hubId The ID of the hub (optional).
     * @param string|null $comment The comment for the status update (optional).
     * @param  int         $id
     * @param  string      $status
     * @param  mixed       $hubId
     * @param  string|null $comment
     * @return mixed
     */
    public function processUpdateOrder(
        int $id,
        string $status,
        $hubId = null,
        string $comment = null
    );
}

