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
     * @param string $tracingLink
     * @param string $trackingNumber
     * @param string $shippingLabelPdf
     * @param string $carrier_title
     * @param bool $overrideExistingData
     * @return string[]
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
