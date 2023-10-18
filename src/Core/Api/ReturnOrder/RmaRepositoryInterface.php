<?php

namespace Omniful\Core\Api\ReturnOrder;

/**
 * OrderInterface for third party modules
 */
interface RmaRepositoryInterface
{
    /**
     * Get Return Order Data By Id
     *
     * @param int $id
     * @return mixed
     */
    public function getReturnOrderDataById(int $id);

    /**
     * Get Return Order
     *
     * @return mixed
     */
    public function getReturnOrder();

    /**
     * Update Rma Status
     *
     * @param int $id
     * @param string $status
     * @param string|null $comment
     * @return mixed
     */
    public function updateRmaStatus(int $id, string $status, string $comment = null);

    /**
     * Return approve
     *
     * @param mixed $rmaEntityId
     * @param mixed $returnApprove
     * @return mixed
     */
    public function approveRma(
        $rmaEntityId,
        $returnApprove = []
    );

    /**
     * Return reject
     *
     * @param mixed $rmaEntityId
     * @param mixed $returnReject
     * @return mixed
     */
    public function rejectRma(
        $rmaEntityId,
        $returnReject = []
    );

    /**
     * Return reject
     *
     * @param mixed $rmaEntityId
     * @param mixed $returnAuthorize
     * @return mixed
     */
    public function authorizeRma(
        $rmaEntityId,
        $returnAuthorize = []
    );

    /**
     * Update Shipment
     *
     * @param string|null $rmaEntityId
     * @param string|null $carrier
     * @param string|null $title
     * @param string|null $trackingNumber
     * @param string|null $shippingLabel
     * @param string|null $packages
     * @param string|null $methodTitle
     * @param string|null $methodCode
     * @param string|null $price
     * @return mixed
     */
    public function updateShipment(
        $rmaEntityId = null,
        $carrier = null,
        $title = null,
        $trackingNumber = null,
        $shippingLabel = null,
        $packages = null,
        $methodTitle = null,
        $methodCode = null,
        $price = null
    );

    /**
     * Get return attributes data.
     *
     * @return array
     */
    public function getReturnAttributes();
}
