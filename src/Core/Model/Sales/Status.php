<?php

namespace Omniful\Core\Model\Sales;

use Magento\Framework\Exception\NoSuchEntityException;
use Omniful\Core\Model\Sales\Order as OrderManagement;
use Omniful\Core\Api\Sales\StatusInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;

class Status implements StatusInterface
{
    const STATUS_NEW = "pending";
    const STATUS_PACKED = "packed";
    const STATUS_SHIPPED = "shipped";
    const STATUS_REFUNDED = "refunded";
    const STATUS_DELIVERED = "delivered";
    const STATUS_READY_TO_SHIP = "ready_to_ship";

    protected $invoiceService;
    protected $orderManagement;
    protected $orderRepository;

    /**
     * OrderManagement constructor.
     *
     */
    public function __construct(
        OrderManagement $orderManagement,
        InvoiceService $invoiceService,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->invoiceService = $invoiceService;
        $this->orderRepository = $orderRepository;
        $this->orderManagement = $orderManagement;
    }

    public function processUpdateOrder(
        int $id,
        string $status,
        mixed $hubId = null,
        string $comment = null
    ): array {
        $responseData = [];

        try {
            $order = $this->orderRepository->get($id);

            if ($order === null) {
                throw new NoSuchEntityException(__("Order not found."));
            }

            // Check if status is "ready_to_ship" or "shipped" or "delivered"
            if (
                $status === self::STATUS_READY_TO_SHIP ||
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
                $order->setData("fulfillment_status", "Hub Assigned");
            }

            // Add a comment to the order (if present)
            if ($comment !== null && $comment !== "") {
                $order->addCommentToStatusHistory($comment);
            }

            $order->save();

            $orderData = $this->orderManagement->getOrderData($order);

            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => "Success",
                "data" => $orderData,
            ];
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => __(
                    "Failed to update order status: " . $e->getMessage()
                ),
            ];
        }

        return $responseData;
    }

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
