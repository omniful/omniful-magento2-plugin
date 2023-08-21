<?php

namespace Omniful\Core\Api\Sales;

/**
 * ShipmentInterface for third party modules
 */
interface ShipmentInterface
{
    /**
     * Create Order Shipment
     *
     * @param int $id The ID of the order to create a shipment for.
     * @param string $tracking_number The tracking number for the shipment.
     * @param mixed $item
     * @param string $tracking_link The tracking link for the shipment.
     * @param string $shipping_label_pdf The shipping label PDF for the shipment.
     * @param string $carrier_title The carrier title for the shipment (optional).
     * @param bool $override_exist_data Whether to override existing shipment data (optional, default: false).
     * @return mixed
     */
    public function processShipment(
        int $id,
        string $tracking_number,
        mixed $items,
        string $tracking_link = null,
        string $shipping_label_pdf = null,
        string $carrier_title = null,
        bool $override_exist_data = false
    );
}
