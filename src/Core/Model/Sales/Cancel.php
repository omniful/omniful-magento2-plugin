<?php

namespace Omniful\Core\Model\Sales;

use Omniful\Core\Api\Sales\CancelInterface;
use Magento\Sales\Api\OrderManagementInterface as MagentoOrderManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Sales\Order as OrderManagement;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Sales\Api\InvoiceRepositoryInterface;

class Cancel implements CancelInterface
{
    const UN_FULFILLED = "Un FulFilled";

    protected $logger;
    protected $orderManagement;
    protected $orderRepository;
    protected $invoiceRepository;
    protected $creditmemoService;
    protected $creditmemoFactory;
    protected $magentoOrderManagementInterface;

    const DEFAULT_CANCEL_REASON = "Omniful Side";
    const OMNIFUL_CANCEL_REASON = "omniful_cancel_reason";
    const STATE_CANCELED = \Magento\Sales\Model\Order::STATE_CANCELED;

    /**
     * OrderManagement constructor.
     */
    public function __construct(
        Logger $logger,
        OrderManagement $orderManagement,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService,
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        MagentoOrderManagementInterface $magentoOrderManagementInterface
    ) {
        $this->logger = $logger;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->creditmemoService = $creditmemoService;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->magentoOrderManagementInterface = $magentoOrderManagementInterface;
    }

    /**
     * Cancel Magento 2 order and add cancel reason as custom order attribute
     *
     * @param int $orderId
     * @param string $cancel_reason
     * @return array
     * @throws CouldNotCancelException
     * @throws LocalizedException
     */
    public function processCancel(
        $orderId,
        $cancel_reason = self::DEFAULT_CANCEL_REASON
    ) {
        try {
            // Load the order
            $order = $this->orderRepository->get($orderId);

            // Check if the order state is "complete", and throw an error if it is
            if (
                $order->getState() ===
                \Magento\Sales\Model\Order::STATE_COMPLETE
            ) {
                throw new LocalizedException(
                    __(
                        "The order cannot be canceled because it is already complete."
                    )
                );
            }

            // Check if the order is already canceled
            if ($order->isCanceled()) {
                throw new LocalizedException(
                    __("The order is already canceled.")
                );
            }

            // Cancel the order
            $this->magentoOrderManagementInterface->cancel($orderId);

            // Set the cancel reason as a custom attribute
            $order->setData(self::OMNIFUL_CANCEL_REASON, $cancel_reason);
            $order
                ->getResource()
                ->saveAttribute($order, self::OMNIFUL_CANCEL_REASON);

            // Add a comment to the order if the comment value is not empty or null
            if (!empty($cancel_reason)) {
                $order
                    ->addStatusHistoryComment($cancel_reason)
                    ->setIsCustomerNotified(false);
            }

            // Refund and create a credit memo if there are invoices
            if ($order->hasInvoices()) {
                foreach ($order->getInvoiceCollection() as $invoice) {
                    // Refund the invoice offline
                    $creditMemo = $this->creditmemoFactory->createByOrder(
                        $order
                    );
                    $creditMemo
                        ->setInvoice($invoice)
                        ->setState(
                            \Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED
                        )
                        ->register();

                    $this->creditmemoService->refund($creditMemo);

                    // Add a comment to the invoice indicating the refund details
                    $refundAmount = $creditMemo->getGrandTotal();
                    $refundMethod = "Offline Refund";
                    $refundComment =
                        "Refunded amount: " .
                        $refundAmount .
                        " via " .
                        $refundMethod;
                    $invoice
                        ->addComment($refundComment)
                        ->setIsVisibleOnFront(false)
                        ->save();

                    // Add a comment to the order indicating the refund details
                    $order
                        ->addStatusHistoryComment($refundComment)
                        ->setIsCustomerNotified(false);
                }
            }

            // Set fulfillment_status attribute to "Un Fulfilled"
            $order->setData("fulfillment_status", self::UN_FULFILLED);

            // Save the order
            $this->orderRepository->save($order);

            $orderData = $this->orderManagement->getOrderData($order);

            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => "Success",
                "data" => $orderData,
            ];

            return $responseData;
        } catch (LocalizedException $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => __(
                    "Could not cancel the order: %1",
                    $e->getMessage()
                ),
            ];

            return $responseData;
        } catch (\Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => __(
                    "An error occurred while canceling the order. %1",
                    $e->getMessage()
                ),
            ];

            return $responseData;
        }
    }
}
