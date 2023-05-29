<?php

namespace Omniful\Core\Model\Sales;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Creditmemo\ItemFactory;
use Magento\Sales\Model\Order\Invoice as InvoiceManagement;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Model\Service\OrderService;
use Omniful\Core\Api\Sales\RefundInterface;
use Omniful\Core\Logger\Logger;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Omniful\Core\Model\Sales\Order as OrderManagement;
use Magento\Sales\Api\OrderItemRepositoryInterface;

class Refund implements RefundInterface
{
    const UN_FULFILLED = "Un FulFilled";

    protected $logger;
    protected $orderService;
    protected $orderManagement;
    protected $orderRepository;
    protected $invoiceManagement;
    protected $creditmemoFactory;
    protected $creditmemoService;
    protected $orderItemRepository;
    protected $creditmemoRepository;
    protected $creditmemoItemFactory;

    /**
     * Refund constructor.
     */
    public function __construct(
        Logger $logger,
        OrderService $orderService,
        OrderManagement $orderManagement,
        ItemFactory $creditmemoItemFactory,
        CreditmemoFactory $creditmemoFactory,
        InvoiceManagement $invoiceManagement,
        CreditmemoService $creditmemoService,
        OrderRepositoryInterface $orderRepository,
        OrderItemRepositoryInterface $orderItemRepository,
        CreditmemoRepositoryInterface $creditmemoRepository
    ) {
        $this->logger = $logger;
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
        $this->orderManagement = $orderManagement;
        $this->invoiceManagement = $invoiceManagement;
        $this->creditmemoService = $creditmemoService;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->orderItemRepository = $orderItemRepository;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->creditmemoItemFactory = $creditmemoItemFactory;
    }

    /**
     * Create credit memo for specified order items and mark order status as "On Hold"
     *
     * @param int $id
     * @param mixed $items
     * @throws \Exception
     */
    public function processRefund(int $id, mixed $items): array
    {
        $responseData = [];

        try {
            // Load the order
            $order = $this->orderRepository->get($id);

            // Check if the order is invoiced
            if (!$order->hasInvoices()) {
                // Create invoice for the order
                $invoice = $this->createInvoice($order);

                // If invoice creation fails
                if (!$invoice) {
                    throw new \Exception(
                        __(
                            "Could not create invoice for Order ID: %1",
                            $order->getId()
                        )
                    );
                }
            }

            // Get the order invoice collection
            $invoices = $order->getInvoiceCollection();

            // Refund each invoice
            foreach ($invoices as $invoice) {
                // Load the invoice by ID
                $invoice = $this->invoiceManagement->load($invoice->getId());

                // Check if the invoice is not already refunded
                if (!$invoice->isCanceled()) {
                    // Prepare credit memo items
                    $itemsToRefund = [];
                    foreach ($order->getAllItems() as $orderItem) {
                        if (!$orderItem->getQtyToRefund()) {
                            continue;
                        }
                        $itemsToRefund[$orderItem->getId()] = [
                            "qty" => $orderItem->getQtyToRefund(),
                            "price" => $orderItem->getPrice(),
                            "row_total" => $orderItem->getRowTotal(),
                        ];
                    }

                    // Create credit memo
                    $creditmemo = $this->creditmemoFactory->createByOrder(
                        $order
                    );
                    $creditmemo->setPaymentRefundDisallowed(true);

                    // Refund the credit memo
                    $this->creditmemoService->refund($creditmemo);

                    // Save credit memo
                    $creditmemo->save();

                    // Save order
                    $this->orderRepository->save($order);

                    // Get the updated order data
                    $orderData = $this->orderManagement->getOrderData($order);

                    // Add the response data
                    $responseData[] = [
                        "httpCode" => 200,
                        "status" => true,
                        "message" => "Success",
                        "data" => $orderData,
                    ];
                } else {
                    throw new \Exception(
                        __(
                            "Invoice ID: " .
                                $invoice->getId() .
                                " is already refunded."
                        )
                    );
                }
            }

            // Return the response data
            return $responseData;
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => __(
                    "Could not refund the order: %1",
                    $e->getMessage()
                ),
            ];

            return $responseData;
        }
    }

    public function createInvoice($order)
    {
        // Prepare invoice
        $invoice = $order->prepareInvoice();

        // Set invoice as requested
        $invoice->setRequestedCaptureCase(
            \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE
        );

        // Register the invoice
        $invoice->register();

        // Save the invoice
        $invoice->save();

        // Update the order
        $order
            ->addRelatedObject($invoice)
            ->setTotalInvoiced(
                $order->getTotalInvoiced() + $invoice->getGrandTotal()
            )
            ->setBaseTotalInvoiced(
                $order->getBaseTotalInvoiced() + $invoice->getBaseGrandTotal()
            )
            ->save();

        return $invoice;
    }
}
