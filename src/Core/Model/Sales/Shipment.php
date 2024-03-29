<?php

namespace Omniful\Core\Model\Sales;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Convert\OrderFactory as OrderConvertFactory;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Store\Api\StoreRepositoryInterface;
use Omniful\Core\Api\Sales\ShipmentInterface;
use Omniful\Core\Helper\Data;
use Omniful\Core\Logger\Logger;

class Shipment implements ShipmentInterface
{
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
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var Http
     */
    private $request;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private $searchCriteriaBuilderFactory;
    /**
     * @var SourceRepositoryInterface
     */
    private $sourceRepository;
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

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
     * @param StoreRepositoryInterface $storeRepository
     * @param SourceRepositoryInterface $sourceRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
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
        StoreRepositoryInterface $storeRepository,
        SourceRepositoryInterface $sourceRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        ShipmentRepositoryInterface $shipmentRepository,
        ShipmentTrackInterfaceFactory $shipmentTrackFactory,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->trackFactory = $trackFactory;
        $this->shipmentFactory = $shipmentFactory;
        $this->orderRepository = $orderRepository;
        $this->sourceRepository = $sourceRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->orderConvertFactory = $orderConvertFactory;
        $this->shipmentTrackFactory = $shipmentTrackFactory;
        $this->helper = $helper;
        $this->request = $request;
        $this->storeRepository = $storeRepository;
    }

    /**
     * Add tracking information to an existing shipment
     *
     * @param int $id
     * @param string $tracking_number
     * @param mixed $items
     * @param string|null $tracking_link
     * @param string|null $shipping_label_pdf
     * @param string|null $carrier_title
     * @param bool|bool $override_exist_data
     * @return mixed
     */
    public function processShipment(
        int $id,
        string $tracking_number,
        $items = null,
        string $tracking_link = null,
        string $shipping_label_pdf = null,
        string $carrier_title = null,
        bool $override_exist_data = false
    ) {
        // Validate input data
        /*if (empty($tracking_number) ||
            empty($tracking_link) ||
            empty($shipping_label_pdf)
        ) {
            $errorMessage = self::INVALID_DATA_ERROR_MESSAGE;
            throw new LocalizedException(
                __($errorMessage)
            );
        }*/

        try {
            $order = $this->orderRepository->get($id);
            $apiUrl = $this->request->getUriString();
            $storeCodeApi = $this->helper->getStoreCodeByApi($apiUrl);
            $storeCode = $this->storeRepository->get($order->getStoreId())->getCode();
            if ($storeCodeApi && $storeCodeApi !== $storeCode) {
                return $this->helper->getResponseStatus(
                    __("Order not found."),
                    500,
                    false,
                    $data = null,
                    $pageData = null,
                    $nestedArray = true
                );
            }
            $status = $order->getStatus();
            // Check if the order status allows adding tracking information
            if (in_array($status, self::IGNORED_STATUSES)) {
                $errorMessage = self::INVALID_STATUS_ERROR_MESSAGE;
                throw new LocalizedException(
                    __($errorMessage, $status)
                );
            }

            // Validate tracking link
            if (!preg_match(self::URL_PATTERN, $tracking_link)) {
                $errorMessage = self::INVALID_LINK_ERROR_MESSAGE;
                throw new LocalizedException(
                    __($errorMessage)
                );
            }

            if (!$order->canShip() && !$override_exist_data) {
                throw new LocalizedException(
                    __('You can\'t create a shipment.')
                );
            }

            $shipment = $this->orderConvertFactory
                ->create()
                ->toShipment($order);
            if (!empty($items)) {
                $shipment = $this->getPartialShipmentData($order, $shipment, $items);
            } else {
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
                //->setTracingLink($tracking_link)
                ->setDescription($carrier_title)
                ->setTrackNumber($tracking_number);
            if ($tracking_link) {
                $track->setTracingLink($tracking_link);
            }

            if ($shipping_label_pdf) {
                $track->setShippingLabelPdf($shipping_label_pdf);
            }

            // Add the comment to the shipment
            $shipment->addComment(__($comment));
            $shipment->addTrack($track);
            $bodyParameters = $this->request->getBodyParams();
            if (isset($bodyParameters["source_code"])) {
                $shipment->getExtensionAttributes()->setSourceCode($bodyParameters["source_code"]);
            } else {
                $sourcesCode = $this->getDefaultSourceCode();
                $shipment->getExtensionAttributes()->setSourceCode($sourcesCode);
            }
            $commentText =
                "Order Shipment has been Generated and you can print the <a href='" .
                $shipping_label_pdf .
                "' target='_blank'>AWB</a> now.";

            $shipment->addComment($commentText);
            $shipment->save();
            $shipment->getOrder()->save();

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
        } catch (Exception $e) {
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
     * Get Default Source Code
     *
     * @return string|null
     */
    public function getDefaultSourceCode()
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteria = $searchCriteriaBuilder->create();
        $sources = $this->sourceRepository->getList($searchCriteria)->getItems();
        $sourcesCode = 'Default';
        foreach ($sources as $source) {
            $sourcesCode = $source->getSourceCode();
        }
        return $sourcesCode;
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
                $shippingLabelPdf = $track->getShippingLabelPdf();
                $carrierTitle = $track->getDescription() ?: __("Custom");
                $shipmentTracking[] = [
                    "title" => (string)$carrierTitle,
                    "code" => (string)$this->carrier_code,
                    "tracing_link" => (string)$trackingLink,
                    "tracking_number" => (string)$tracking_number,
                    "shipping_label_pdf" => (string)$shippingLabelPdf,
                ];
            }
        }
        return $shipmentTracking;
    }

    /**
     * Get Partial Shipment Data
     *
     * @param mixed $order
     * @param mixed $shipment
     * @param mixed $items
     * @return mixed
     * @throws LocalizedException
     */
    public function getPartialShipmentData($order, $shipment, $items)
    {
        try {
            foreach ($items as $item) {
                foreach ($order->getAllItems() as $orderItem) {
                    if (isset($item['sku']) && isset($item['qty']) && $item['sku'] == $orderItem['sku']) {
                        if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                            continue;
                        }
                        if ($orderItem->getQtyToShip() >= $item['qty']) {
                            $qtyShipped = $item['qty'];
                        } else {
                            $qtyShipped = $orderItem->getQtyToShip();
                        }
                        $shipmentItem = $this->orderConvertFactory
                            ->create()
                            ->itemToShipmentItem($orderItem)
                            ->setQty($qtyShipped);
                        $shipment->addItem($shipmentItem);
                    }
                }
            }
            return $shipment;
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __($e->getMessage()),
                500,
                false
            );
        }
    }
}
