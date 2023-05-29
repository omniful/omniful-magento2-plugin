<?php

namespace Omniful\Core\Api\Sales;

/**
 * StatusInterface for third party modules
 */
interface StatusInterface
{
    /**
     * Update order status.
     *
     * @param int $id
     * @param string $status
     * @param  mixed $hubId
     * @param  string $comment
     * @return string[]
     */
    public function processUpdateOrder(
        int $id,
        string $status,
        mixed $hubId = null,
        string $comment = null
    );
}
