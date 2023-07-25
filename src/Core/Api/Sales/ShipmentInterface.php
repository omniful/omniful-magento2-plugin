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
     * @param int $id
     * @param string $tracking_link
     * @param string $tracking_number
     * @param string $shipping_label_pdf
     * @param string $carrier_title
     * @param bool $override_exist_data
     * @return mixed
     */
    public function processShipment(
        int $id,
        string $tracking_link,
        string $tracking_number,
        string $shipping_label_pdf,
        string $carrier_title = null,
        bool $override_exist_data = false
    );
}
