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
use Omniful\Core\Helper\Data;

class Refund implements RefundInterface
{
    public const UN_FULFILLED = "Un FulFilled";
    /**
     * @var Logger
     */
    protected $logger;
    /**
     * @var OrderService
     */
    protected $orderService;
    /**
     * @var Order
     */
    protected $orderManagement;
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var InvoiceManagement
     */
    protected $invoiceManagement;
    /**
     * @var CreditmemoFactory
     */
    protected $creditmemoFactory;
    /**
     * @var CreditmemoService
     */
    protected $creditmemoService;
    /**
     * @var OrderItemRepositoryInterface
     */
    protected $orderItemRepository;
    /**
     * @var CreditmemoRepositoryInterface
     */
    protected $creditmemoRepository;
    /**
     * @var ItemFactory
     */
    protected $creditmemoItemFactory;
    /**
     * @var Data
     */
    private $helper;

    /**
     * Refund constructor.
     *
     * @param Logger $logger
     * @param OrderService $orderService
     * @param Order $orderManagement
     * @param ItemFactory $creditmemoItemFactory
     * @param CreditmemoFactory $creditmemoFactory
     * @param InvoiceManagement $invoiceManagement
     * @param CreditmemoService $creditmemoService
     * @param Data $helper
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     */
    public function __construct(
        Logger $logger,
        OrderService $orderService,
        OrderManagement $orderManagement,
        ItemFactory $creditmemoItemFactory,
        CreditmemoFactory $creditmemoFactory,
        InvoiceManagement $invoiceManagement,
        CreditmemoService $creditmemoService,
        Data $helper,
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
        $this->helper = $helper;
    }

    /**
     * Create credit memo for specified order items and mark order status as "On Hold"
     *
     * @param int $id
     * @param array $items
     * @return array
     */
    public function processRefund(int $id, $items): array
    {
        try {
            // Load the order
            $order = $this->orderRepository->get($id);

            // Check if the order is invoiced
            if (!$order->hasInvoices()) {
                // Create invoice for the order
                $invoice = $this->createInvoice($order);

                // If invoice creation fails
                if (!$invoice) {
                    throw new \Magento\Framework\Exception\AlreadyExistsException(
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
                    return $this->helper->getResponseStatus(
                        "Success",
                        200,
                        true,
                        $orderData,
                        $pageData = null,
                        $nestedArray = true
                    );
                } else {
                    throw new \Magento\Framework\Exception\AlreadyExistsException(
                        __(
                            "Invoice ID: " .
                                $invoice->getId() .
                                " is already refunded."
                        )
                    );
                }
            }
        } catch (\Exception $e) {
            return $this->helper->getResponseStatus(
                __("Could not refund the order: %1", $e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Create Invoice
     *
     * @param  mixed $order
     * @return mixed
     */
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
