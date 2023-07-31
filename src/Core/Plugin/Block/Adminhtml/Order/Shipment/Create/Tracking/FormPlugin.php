<?php

namespace Omniful\Core\Plugin\Block\Adminhtml\Order\Shipment\Create\Tracking;

use Magento\Sales\Block\Adminhtml\Order\Shipment\Create\Tracking\Form;

class FormPlugin
{
    /**
     * Add custom carrier option to the carrier dropdown
     *
     * @param  Form $subject
     * @param  array $result
     * @return array
     */
    public function afterGetCarriersOptions(Form $subject, $result)
    {
        // Add custom carrier option
        $result[] = [
            "label" => __("Omniful Express"),
            "value" => "omniful_express",
        ];

        return $result;
    }
}
