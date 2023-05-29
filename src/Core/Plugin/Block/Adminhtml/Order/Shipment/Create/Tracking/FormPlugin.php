<?php

namespace Omniful\Core\Plugin\Block\Adminhtml\Order\Shipment\Create\Tracking;

class FormPlugin
{
    /**
     * Add custom carrier option to the carrier dropdown
     *
     * @param \Magento\Sales\Block\Adminhtml\Order\Shipment\Create\Tracking\Form $subject
     * @param array $result
     * @return array
     */
    public function afterGetCarriersOptions(
        \Magento\Sales\Block\Adminhtml\Order\Shipment\Create\Tracking\Form $subject,
        $result
    ) {
        // Add custom carrier option
        $result[] = [
            "label" => __("Omniful Express"),
            "value" => "omniful_express",
        ];

        return $result;
    }
}
