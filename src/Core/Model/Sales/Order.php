<?php

namespace Omniful\Core\Model\Sales;

use Omniful\Core\Api\Sales\OrderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\App\RequestInterface;
use Omniful\Core\Model\Sales\Shipment as ShipmentManagement;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Omniful\Core\Helper\Countries;

class Order implements OrderInterface
{
    /**
     * @var RequestInterface
     */
    protected $request;

    protected $orderRepository;

    protected $shipmentManagement;

    protected $countriesHelper;
    protected $orderCollectionFactory;

    /**
     * OrderManagement constructor.
     *
     */
    public function __construct(
        RequestInterface $request,
        Countries $countriesHelper,
        ShipmentManagement $shipmentManagement,
        CollectionFactory $orderCollectionFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->request = $request;
        $this->countriesHelper = $countriesHelper;
        $this->orderRepository = $orderRepository;
        $this->shipmentManagement = $shipmentManagement;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * @inheritdoc
     */
    public function getOrders()
    {
        $page = (int) $this->request->getParam('page') ?: 1;
        $limit = (int) $this->request->getParam('limit') ?: 200;

        // Default status values to be filtered
        $statuses = $this->request->getHeader('statuses') ?: ['pending', 'processing', 'complete', 'holded', 'pending_payment'];

        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('status', ['in' => $statuses]);
        $orderCollection->setPageSize($limit);
        $orderCollection->setCurPage($page);

        $orderData = [];
        foreach ($orderCollection as $order) {
            $orderData[] = $this->getOrderData($order);
        }

        $totalOrders = $orderCollection->getSize();

        $pageInfo = [
            'current_page' => $page,
            'per_page' => $limit,
            'total_count' => $totalOrders,
            'total_pages' => ceil($totalOrders / $limit),
        ];

        $responseData[] = [
            'httpCode' => 200,
            'status' => true,
            'message' => 'Success',
            'data' => $orderData,
            'page_info' => $pageInfo,
        ];

        return $responseData;
    }

    /**
     * @inheritdoc
     */
    public function getOrderById($orderId)
    {
        $order = $this->getOrderByIdentifier($orderId);
        if (!$order) {
            throw new NoSuchEntityException(__('Order not found.'));
        }

        $orderData = $this->getOrderData($order);

        $responseData[] = [
            'httpCode' => 200,
            'status' => true,
            'message' => 'Success',
            'data' => $orderData,
        ];

        return $responseData;
    }

    /**
     * Get order data.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getOrderData($order)
    {
        $orderItems = [];
        $shipmentTracking = [];
        $customerData = [];
        $invoiceData = [];
        $paymentMethod = [];
        $shippingAddress = [];

        try {
            $shippingData = $this->shipmentManagement->getShipmentData($order);
            $shipmentTracking = [];
            $customerId = $order->getCustomerId();

            foreach ($shippingData as $data) {
                $shipmentTracking[] = [
                    'track_number' => (string) $data['tracking_number'],
                    'title' => (string) $data['title'],
                    'carrier_code' => (string) $data['code'],
                    'tracing_link' => (string) $data['tracing_link'],
                    'tracking_number' => (string) $data['tracking_number'],
                    'shipping_label_pdf' => (string) $data['shipping_label_pdf'],
                ];
            }

            $customerData = [
                'first_name' => (string) $order->getBillingAddress()->getFirstName(),
                'last_name' => (string) $order->getBillingAddress()->getLastName(),
                'email' => (string) $order->getBillingAddress()->getEmail(),
                'phone' => (string) $order->getBillingAddress()->getTelephone(),
                'company' => (string) $order->getBillingAddress()->getCompany(),
                'address_1' => (string) $order->getBillingAddress()->getStreetLine1(),
                'address_2' => (string) $order->getBillingAddress()->getStreetLine2(),
                'city' => (string) $order->getBillingAddress()->getCity(),
                'state' => (string) $order->getBillingAddress()->getRegion(),
                'postcode' => (string) $order->getBillingAddress()->getPostcode(),
                'country' => (string) $order->getBillingAddress()->getCountryId(),
            ];

            foreach ($order->getItems() as $item) {
                $product = $item->getProduct();
                $orderItems[] = [
                    'id' => (int) $item->getId(),
                    'sku' => (string) $product->getSku(),
                    'product_id' => (int) $product->getId(),
                    'name' => (string) $product->getName(),
                    'barcode' => $product->getCustomAttribute('omniful_barcode_attribute') ? (string) $product->getCustomAttribute('omniful_barcode_attribute')->getValue() : null,
                    'quantity' => (float) $item->getQtyOrdered(),
                    'price' => (float) $item->getPrice(),
                    'subtotal' => (float) $item->getRowTotal(),
                    'total' => (float) $item->getRowTotalInclTax(),
                    'tax' => (float) $item->getTaxAmount(),
                ];

            }

            $invoiceData = [
                'currency' => (string) $order->getOrderCurrencyCode(),
                'subtotal' => (float) $order->getSubtotal(),
                'shipping_price' => (float) $order->getShippingAmount(),
                'tax' => (float) $order->getTaxAmount(),
                'discount' => (float) $order->getDiscountAmount(),
                'total' => (float) $order->getGrandTotal(),
            ];

            $paymentMethod = [
                'code' => (string) $order->getPayment()->getMethod(),
                'title' => (string) $order->getPayment()->getMethodInstance()->getTitle(),
                'is_cash_on_delivery' => $this->isCashOnDelivery($order),
            ];

            $shippingAddress = $this->getShippingAddressData($customerId, $order->getShippingAddress()->getData());

            // Retrieve totals
            $totals = [
                'subtotal' => [
                    'title' => __('Subtotal'),
                    'value' => (float) $order->getSubtotal(),
                    'formatted_value' => strip_tags($order->formatPrice($order->getSubtotal())),
                ],
                'shipping' => [
                    'title' => __('Shipping'),
                    'value' => (float) $order->getShippingAmount(),
                    'formatted_value' => strip_tags($order->formatPrice($order->getShippingAmount())),
                ],
                'tax' => [
                    'title' => __('Tax'),
                    'value' => (float) $order->getTaxAmount(),
                    'formatted_value' => strip_tags($order->formatPrice($order->getTaxAmount())),
                ],
                'discount' => [
                    'title' => __('Discount'),
                    'value' => (float) $order->getDiscountAmount(),
                    'formatted_value' => strip_tags($order->formatPrice($order->getDiscountAmount())),
                ],
                'total' => [
                    'title' => __('Total'),
                    'value' => (float) $order->getGrandTotal(),
                    'formatted_value' => strip_tags($order->formatPrice($order->getGrandTotal())),
                ],
                'total_refunded' => [
                    'title' => __('Total Refunded'),
                    'value' => (float) $order->getTotalRefunded(),
                    'formatted_value' => strip_tags($order->formatPrice($order->getTotalRefunded())),
                ],
                'total_paid' => [
                    'title' => __('Total Paid'),
                    'value' => (float) $order->getTotalPaid(),
                    'formatted_value' => strip_tags($order->formatPrice($order->getTotalPaid())),
                ],
                'total_due' => [
                    'title' => __('Total Due'),
                    'value' => (float) $order->getTotalDue(),
                    'formatted_value' => strip_tags($order->formatPrice($order->getTotalDue())),
                ],
            ];

            $orderData = [
                'id' => (int) $order->getEntityId(),
                'status' => [
                    'code' => (string) $order->getStatus(),
                    'label' => $order->getStatusLabel(),
                    'state' => $order->getState()
                ],
                'currency' => (string) $order->getOrderCurrencyCode(),
                'shipping_method' => (string) $order->getShippingMethod(),
                'total' => (float) $order->getGrandTotal(),
                'subtotal' => (float) $order->getSubtotal(),
                'tax_total' => (float) $order->getTaxAmount(),
                'discount_total' => (float) $order->getDiscountAmount(),
                'created_at' => $order->getCreatedAt() ? $order->getCreatedAt() : '',
                'invoice' => $invoiceData,
                'customer' => $customerData,
                'order_items' => $orderItems,
                'payment_method' => $paymentMethod,
                'shipping_address' => $shippingAddress,
                'cancel_reason' => $this->getCancelReason($order),
                'totals' => $totals,
                'shipments' => $shipmentTracking,
            ];

            return $orderData;
        } catch (NoSuchEntityException $e) {
            $responseData[] = [
                'httpCode' => 500,
                'status' => false,
                'message' => __('Order not found.'),
            ];

            return $responseData;
        }
    }



    /**
     * Get order by order identifier
     *
     * @param int|string $orderIdentifier
     * @return OrderInterface|null
     * @throws NoSuchEntityException
     */
    protected function getOrderByIdentifier($orderIdentifier)
    {
        if (is_numeric($orderIdentifier)) {
            $order = $this->orderRepository->get($orderIdentifier);
        } else {
            $order = $this->orderRepository->getByIncrementId($orderIdentifier);
        }

        if (!$order->getEntityId()) {
            throw new NoSuchEntityException(__('Order not found.'));
        }

        return $order;
    }

    /**
     * Check if the order payment method is cash on delivery
     *
     * @param OrderInterface $order
     * @return bool
     */
    protected function isCashOnDelivery($order)
    {
        return $order->getPayment()->getMethod() === 'cashondelivery';
    }

    /**
     * Get cancel reason for order
     *
     * @param OrderInterface $order
     * @return string|null
     */
    protected function getCancelReason($order)
    {
        return $order->getData('omniful_cancel_reason') ?: null;
    }

    public function getShippingAddressData($customerId, $address)
    {
        $country = $this->countriesHelper->getCountryByCode($address["country_id"]);

        $shippingAddressData["first_name"] = $address["firstname"];
        $shippingAddressData["last_name"] = $address["lastname"];
        $shippingAddressData["email"] = $address["email"];
        $shippingAddressData["country_id"] = $address["country_id"];
        $shippingAddressData["country"] = ucwords($country["name"]);
        $shippingAddressData["region"] = $address["region"] ?: "";
        $shippingAddressData["city"] = $address["city"];
        $shippingAddressData["street"] = $address["street"];
        $shippingAddressData["postcode"] = $address["postcode"];
        $shippingAddressData["addressType"] = $address["address_type"];
        $shippingAddressData["company"] = $address["company"] ?: "";
        $shippingAddressData["phone"] = $address["telephone"] ?: "";

        return $shippingAddressData;
    }
}