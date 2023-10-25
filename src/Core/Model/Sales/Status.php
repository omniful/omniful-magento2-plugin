<?php

namespace Omniful\Core\Model\Sales;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Omniful\Core\Model\Sales\Order as OrderManagement;
use Omniful\Core\Api\Sales\StatusInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Omniful\Core\Helper\Data;

class Status implements StatusInterface
{
    public const STATUS_NEW = "pending";
    public const STATUS_PACKED = "packed";
    public const STATUS_SHIPPED = "shipped";
    public const STATUS_REFUNDED = "refunded";
    public const STATUS_DELIVERED = "delivered";
    public const STATUS_READY_TO_SHIP = "ready_to_ship";
    /**
     * @var InvoiceService
     */
    protected $invoiceService;
    /**
     * @var \Omniful\Core\Model\Sales\Order
     */
    protected $orderManagement;
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * Status constructor.
     *
     * @param \Omniful\Core\Model\Sales\Order $orderManagement
     * @param InvoiceService $invoiceService
     * @param Data $helper
     * @param RequestInterface $request
     * @param StoreRepositoryInterface $storeRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderManagement $orderManagement,
        InvoiceService $invoiceService,
        Data $helper,
        RequestInterface $request,
        StoreRepositoryInterface $storeRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->invoiceService = $invoiceService;
        $this->orderRepository = $orderRepository;
        $this->orderManagement = $orderManagement;
        $this->helper = $helper;
        $this->request = $request;
        $this->storeRepository = $storeRepository;
    }

    /**
     * Process Update Order
     *
     * @param int $id
     * @param string $status
     * @param mixed|null $hubId
     * @param string $comment
     * @return array
     */
    public function processUpdateOrder(
        int $id,
        string $status,
        $hubId = null,
        string $comment = null
    ): array {
        try {

            $customStatus = [
                "pending" => "Pending",
                "packed" => "Packed",
                "processing" => "Hub Assigned",
                "ready_to_ship" => "Ready To Ship",
                "shipped" => "shipped",
                "holded" => "On Hold",
                "canceled" => "Canceled",
                "delivered" => "Delivered",
            ];
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

            if(!$order->canCancel()){
                return $this->helper->getResponseStatus(
                    __("Your order can no longer be cancelled."),
                    500,
                    false,
                    $data = null,
                    $pageData = null,
                    $nestedArray = true
                );
            }
            if ($order === null) {
                throw new NoSuchEntityException(__("Order not found."));
            }

            // Check if status is "ready_to_ship" or "shipped" or "delivered"
            if ($status === self::STATUS_READY_TO_SHIP ||
                $status === self::STATUS_SHIPPED ||
                $status === self::STATUS_DELIVERED
            ) {
                $shipments = $order->getShipmentsCollection();

                // Check if the order has shipments
                if ($shipments->getSize() === 0) {
                    throw new \Exception(
                        __(
                            "Cannot update status. Order does not have any shipments."
                        )
                    );
                }

                // Check if invoice already exists
                $invoices = $order->getInvoiceCollection();

                // Create an invoice if no invoice exists
                if ($invoices->getSize() === 0) {
                    $invoice = $this->invoiceService->prepareInvoice($order);
                    $invoice->register();
                    $invoice->save();
                    $order->addStatusHistoryComment(
                        __("Invoice created automatically."),
                        false
                    );
                    $order->save();
                }
            }

            // Set the order status and state
            $order->setStatus($this->getOrderStatus($status));
            $order->setState($this->getOrderState($status));
            // Set the omniful_hub_id attribute and update the fulfillment status
            if ($hubId !== null) {
                $order->setData("omniful_hub_id", $hubId);
            }

            if (isset($customStatus[$status])) {
                $order->setData("fulfillment_status", $customStatus[$status]);
            }

            // Add a comment to the order (if present)
            if ($comment !== null && $comment !== "") {
                $order->addCommentToStatusHistory($comment);
            }
            $order->save();
            $orderData = $this->orderManagement->getOrderData($order);
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $orderData,
                $pageData = null,
                $nestedArray = true
            );
        } catch (\Exception $e) {
            return $this->helper->getResponseStatus(
                __("Failed to update order status: " . $e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Get Order Status
     *
     * @param string $status
     * @return string
     */
    protected function getOrderStatus(string $status): string
    {
        switch ($status) {
            case "processing":
                return Order::STATE_PROCESSING;
            case "packed":
                return self::STATUS_PACKED;
            case "ready_to_ship":
                return self::STATUS_READY_TO_SHIP;
            case "shipped":
                return self::STATUS_SHIPPED;
            case "canceled":
                return Order::STATE_CANCELED;
            case "closed":
                return Order::STATE_CLOSED;
            case "complete":
                return Order::STATE_COMPLETE;
            case "delivered":
                return self::STATUS_DELIVERED;
            case "fraud":
                return Order::STATE_PAYMENT_REVIEW;
            case "holded":
                return Order::STATUS_HOLDED;
            case "payment_review":
                return Order::STATE_PAYMENT_REVIEW;
            case "pending":
            default:
                return self::STATUS_NEW;
        }
    }

    /**
     * Get Order State
     *
     * @param  string $status
     * @return string
     */
    protected function getOrderState(string $status): string
    {
        switch ($status) {
            case "processing":
                return Order::STATE_PROCESSING;
            case "packed":
                return Order::STATE_PROCESSING;
            case "ready_to_ship":
                return Order::STATE_PROCESSING;
            case "shipped":
                return Order::STATE_COMPLETE;
            case "canceled":
                return Order::STATE_CANCELED;
            case "closed":
                return Order::STATE_CLOSED;
            case "complete":
                return Order::STATE_COMPLETE;
            case "delivered":
                return Order::STATE_COMPLETE;
            case "fraud":
                return Order::STATE_PAYMENT_REVIEW;
            case "holded":
                return Order::STATE_HOLDED;
            case "payment_review":
                return Order::STATE_PAYMENT_REVIEW;
            case "pending":
            default:
                return Order::STATE_NEW;
        }
    }
}
