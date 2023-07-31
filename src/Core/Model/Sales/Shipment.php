<?php

namespace Omniful\Core\Model\Sales;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\OrderRepositoryInterface;
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
use Omniful\Core\Helper\Data;

class Shipment implements ShipmentInterface
{
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var TrackFactory
     */
    protected $trackFactory;
    /**
     * @var ShipmentFactory
     */
    protected $shipmentFactory;
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var ShipmentRepositoryInterface
     */
    protected $shipmentRepository;
    /**
     * @var OrderConvertFactory
     */
    protected $orderConvertFactory;
    /**
     * @var ShipmentTrackInterfaceFactory
     */
    protected $shipmentTrackFactory;
    /**
     * @var string
     */
    protected $carrier_code = "omniful_express";

    public const INVALID_DATA_ERROR_MESSAGE = "Invalid/Missing data provided.";
    public const TRACKING_INFO_ADDED_MESSAGE = "Order tracking information added successfully.";
    public const EXCEPTION_ERROR_MESSAGE = "Could not add tracking information to the order: %1";
    public const INVALID_STATUS_ERROR_MESSAGE = "Cannot add tracking information to an order with the %1 status.";
    public const INVALID_LINK_ERROR_MESSAGE = "Invalid tracking link provided. Please provide a valid website link.";
    public const IGNORED_STATUSES = [
        "refunded",
        "cancelled",
        "failed",
        "delivered",
        "completed",
        "pending",
        "shipped",
    ];
    public const URL_PATTERN = '/^(http|https):\/\/[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,6})+(\/[^\s]*)?$/';
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var Http
     */
    private $request;

    /**
     * Shipment constructor.
     *
     * @param Logger $logger
     * @param TrackFactory $trackFactory
     * @param ShipmentFactory $shipmentFactory
     * @param Request $request
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderConvertFactory $orderConvertFactory
     * @param Data $helper
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param ShipmentTrackInterfaceFactory $shipmentTrackFactory
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Logger $logger,
        TrackFactory $trackFactory,
        ShipmentFactory $shipmentFactory,
        Request $request,
        ScopeConfigInterface $scopeConfig,
        OrderConvertFactory $orderConvertFactory,
        Data $helper,
        ShipmentRepositoryInterface $shipmentRepository,
        ShipmentTrackInterfaceFactory $shipmentTrackFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->trackFactory = $trackFactory;
        $this->shipmentFactory = $shipmentFactory;
        $this->orderRepository = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
        $this->orderConvertFactory = $orderConvertFactory;
        $this->shipmentTrackFactory = $shipmentTrackFactory;
        $this->helper = $helper;
        $this->request = $request;
    }

    /**
     * Add tracking information to an existing shipment
     *
     * @param int $id
     * @param string $tracking_link
     * @param string $tracking_number
     * @param string $shipping_label_pdf
     * @param string $carrier_title
     * @param bool $override_exist_data
     * @return mixed|string[]
     * @throws LocalizedException
     */
    public function processShipment(
        int $id,
        string $tracking_link,
        string $tracking_number,
        string $shipping_label_pdf,
        string $carrier_title = null,
        bool $override_exist_data = false
    ) {
        // Validate input data
        if (empty($tracking_number) ||
            empty($tracking_link) ||
            empty($shipping_label_pdf)
        ) {
            $errorMessage = self::INVALID_DATA_ERROR_MESSAGE;
            throw new \Magento\Framework\Exception\LocalizedException(
                __($errorMessage)
            );
        }

        try {
            $order = $this->orderRepository->get($id);
            $status = $order->getStatus();
            // Check if the order status allows adding tracking information
            if (in_array($status, self::IGNORED_STATUSES)) {
                $errorMessage = self::INVALID_STATUS_ERROR_MESSAGE;
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($errorMessage, $status)
                );
            }

            // Validate tracking link
            if (!preg_match(self::URL_PATTERN, $tracking_link)) {
                $errorMessage = self::INVALID_LINK_ERROR_MESSAGE;
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($errorMessage)
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
            $sourceCode = $this->request->getBodyParams();
            $shipment
                ->getExtensionAttributes()
                ->setSourceCode($sourceCode["source_code"]);
            $shipment->save();
            $shipment->getOrder()->save();

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
            $addedMessage = self::TRACKING_INFO_ADDED_MESSAGE;

            return $this->helper->getResponseStatus(
                __($addedMessage),
                200,
                true,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (\Exception $e) {
            $errorMessage = self::EXCEPTION_ERROR_MESSAGE;
            return $this->helper->getResponseStatus(
                __($errorMessage, $e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Get shipment tracking data for the order
     *
     * @param  OrderInterface $order
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
                $shippingLabelPdf = $track->getShippingLabelPdf();
                $carrierTitle = $track->getDescription() ?: __("Custom");
                $shipmentTracking[] = [
                    "title" => (string) $carrierTitle,
                    "code" => (string) $this->carrier_code,
                    "tracing_link" => (string) $trackingLink,
                    "tracking_number" => (string) $tracking_number,
                    "shipping_label_pdf" => (string) $shippingLabelPdf,
                ];
            }
        }
        return $shipmentTracking;
    }
}
