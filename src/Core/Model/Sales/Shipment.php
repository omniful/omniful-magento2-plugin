<?php

namespace Omniful\Core\Model\Sales;

use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory;
use Omniful\Core\Api\Sales\ShipmentInterface;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Sales\Status;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Convert\OrderFactory as OrderConvertFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Shipment implements ShipmentInterface
{
    protected $logger;
    protected $scopeConfig;
    protected $trackFactory;
    protected $shipmentFactory;
    protected $orderRepository;
    protected $shipmentRepository;
    protected $orderConvertFactory;
    protected $shipmentTrackFactory;
    protected $carrier_code = "omniful_express";

    const INVALID_DATA_ERROR_MESSAGE = "Invalid/Missing data provided.";
    const TRACKING_INFO_ADDED_MESSAGE = "Order tracking information added successfully.";
    const EXCEPTION_ERROR_MESSAGE = "Could not add tracking information to the order: %1";
    const TRACKING_INFO_UPDATED_MESSAGE = "Order tracking information updated successfully.";
    const INVALID_STATUS_ERROR_MESSAGE = "Cannot add tracking information to an order with the %1 status.";
    const INVALID_LINK_ERROR_MESSAGE = "Invalid tracking link provided. Please provide a valid website link.";
    const TRACKING_INFO_OVERRIDE_ERROR_MESSAGE = 'The order already has tracking information. If you wish to override it, please set "override_existing_data" to true.';

    const ALLOWED_STATUSES = [
        "refunded",
        "cancelled",
        "failed",
        "delivered",
        "completed",
        "pending",
        "shipped",
    ];
    const URL_PATTERN = '/^(http|https):\/\/[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,6})+(\/[^\s]*)?$/';

    public function __construct(
        Logger $logger,
        TrackFactory $trackFactory,
        ShipmentFactory $shipmentFactory,
        ScopeConfigInterface $scopeConfig,
        OrderConvertFactory $orderConvertFactory,
        ShipmentRepositoryInterface $shipmentRepository,
        ShipmentTrackInterfaceFactory $shipmentTrackFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->trackFactory = $trackFactory;
        $this->shipmentFactory = $shipmentFactory;
        $this->orderRepository = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
        $this->orderConvertFactory = $orderConvertFactory;
        $this->shipmentTrackFactory = $shipmentTrackFactory;
    }

    /**
     * Add tracking information to an existing shipment
     *
     * @param int $id
     * @param string $tracking_link
     * @param string $tracking_number
     * @param string $shipping_label_pdf
     * @param bool $override_exist_data
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function processShipment(
        int $id,
        string $tracking_link,
        string $tracking_number,
        string $shipping_label_pdf,
        string $carrier_title = null,
        bool $override_exist_data = false
    ) {
        if (!$carrier_title) {
            $carrier_title = $this->scopeConfig->getValue(
                "carriers/" . $this->carrier_code . "/title",
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        }

        var_dump($carrier_title);
        // Validate input data
        if (
            empty($tracking_number) ||
            empty($tracking_link) ||
            empty($shipping_label_pdf)
        ) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __(self::INVALID_DATA_ERROR_MESSAGE)
            );
        }

        try {
            $order = $this->orderRepository->get($id);
            $status = $order->getStatus();

            // Check if the order status allows adding tracking information
            if (in_array($status, self::ALLOWED_STATUSES)) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __(self::INVALID_STATUS_ERROR_MESSAGE, $status)
                );
            }

            // Validate tracking link
            if (!preg_match(self::URL_PATTERN, $tracking_link)) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __(self::INVALID_LINK_ERROR_MESSAGE)
                );
            }

            if (!$order->canShip() && !$override_exist_data) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('You can\'t create a shipment.')
                );
            }

            $shipment = $this->orderConvertFactory
                ->create()
                ->toShipment($order);

            foreach ($order->getAllItems() as $orderItem) {
                // Check if order item has qty to ship or is virtual
                if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                    continue;
                }

                $qtyShipped = $orderItem->getQtyToShip();
                $shipmentItem = $this->orderConvertFactory
                    ->create()
                    ->itemToShipmentItem($orderItem)
                    ->setQty($qtyShipped);
                $shipment->addItem($shipmentItem);
            }

            $shipment->register();
            $shipment->getOrder()->setIsInProcess(true);
            $itemCount = count($order->getAllItems());

            $itemsWeight = 0;
            foreach ($order->getAllItems() as $item) {
                $itemsWeight += $item->getWeight() * $item->getQtyOrdered();
            }

            $comment =
                "a Carrier has been assigned to that shipment via Omniful Core";
            $track = $this->shipmentTrackFactory
                ->create()
                ->setQty($itemCount)
                ->setWeight($itemsWeight)
                ->setTitle($carrier_title)
                ->setCarrierCode($this->carrier_code)
                ->setTracingLink($tracking_link)
                ->setDescription($carrier_title)
                ->setTrackNumber($tracking_number)
                ->setShippingLabelPdf($shipping_label_pdf);

            // Add the comment to the shipment
            $shipment->addComment(__($comment));

            $shipment->addTrack($track);
            $shipment->save();
            $shipment->getOrder()->save();

            $order->setShippingMethod($this->carrier_code);
            $order->setShippingDescription($carrier_title);

            $commentText =
                "Order Shipment has been Generated and you can print the <a href='" .
                $shipping_label_pdf .
                "' target='_blank'>AWB</a> now.";
            $order->addStatusHistoryComment($commentText);

            $orderShipments = $order->getShipmentsCollection();
            $hasShipments = count($orderShipments) ? true : false;

            if ($hasShipments) {
                // Change order status to 'shipped'
                $order->setStatus(Status::STATUS_READY_TO_SHIP);
                $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            }

            $order->save();

            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => __(self::TRACKING_INFO_ADDED_MESSAGE),
            ];

            return $responseData;
        } catch (\Exception $e) {
            // var_dump($e->getMessage());
            // exit;
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => __(
                    self::EXCEPTION_ERROR_MESSAGE,
                    $e->getMessage()
                ),
            ];

            return $responseData;
        }
    }

    /**
     * Get shipment tracking data for the order
     *
     * @param OrderInterface $order
     * @return array
     */
    public function getShipmentData($order)
    {
        $shipmentTracking = [];

        $tracks = $order->getTracksCollection();
        if ($tracks) {
            foreach ($tracks as $track) {
                $trackingLink = $track->getTracingLink();
                $tracking_number = $track->getTrackNumber();
                $shipping_label_pdf = $track->getShippingLabelPdf();
                $shippingTitle = $this->scopeConfig->getValue(
                    "carriers/" . $this->carrier_code . "/title",
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
                $shipmentTracking[] = [
                    "title" => (string) $shippingTitle,
                    "code" => (string) $this->carrier_code,
                    "tracing_link" => (string) $trackingLink,
                    "tracking_number" => (string) $tracking_number,
                    "shipping_label_pdf" => (string) $shipping_label_pdf,
                ];
            }
        }

        return $shipmentTracking;
    }
}
