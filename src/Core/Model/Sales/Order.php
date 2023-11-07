<?php

namespace Omniful\Core\Model\Sales;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface as OrderRepositoryInterfaceAlias;
use Magento\Sales\Model\Order\Invoice as InvoiceManagement;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Api\StoreRepositoryInterface;
use Omniful\Core\Api\Sales\OrderInterface;
use Omniful\Core\Helper\Countries;
use Omniful\Core\Helper\Data;
use Omniful\Core\Model\Sales\Shipment as ShipmentManagement;
use ReflectionClass;
use ReflectionException;

class Order implements OrderInterface
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var OrderRepositoryInterfaceAlias
     */
    protected $orderRepository;

    /**
     * @var Shipment
     */
    protected $shipmentManagement;

    /**
     * @var Countries
     */
    protected $countriesHelper;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var Data
     */
    private $helper;
    /**
     * @var InvoiceManagement
     */
    private $invoiceManagement;
    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditMemoRepository;
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;
    /**
     * @var Json
     */
    private Json $json;

    /**
     * Order constructor.
     *
     * @param Data $helper
     * @param RequestInterface $request
     * @param Countries $countriesHelper
     * @param Shipment $shipmentManagement
     * @param CreditmemoRepositoryInterface $creditMemoRepository
     * @param InvoiceManagement $invoiceManagement
     * @param StoreRepositoryInterface $storeRepository
     * @param CollectionFactory $orderCollectionFactory
     * @param OrderRepositoryInterfaceAlias $orderRepository
     * @param Json $json
     */
    public function __construct(
        Data $helper,
        RequestInterface $request,
        Countries $countriesHelper,
        ShipmentManagement $shipmentManagement,
        CreditmemoRepositoryInterface $creditMemoRepository,
        InvoiceManagement $invoiceManagement,
        StoreRepositoryInterface $storeRepository,
        CollectionFactory $orderCollectionFactory,
        OrderRepositoryInterfaceAlias $orderRepository,
        Json $json
    ) {
        $this->helper = $helper;
        $this->request = $request;
        $this->countriesHelper = $countriesHelper;
        $this->orderRepository = $orderRepository;
        $this->shipmentManagement = $shipmentManagement;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->invoiceManagement = $invoiceManagement;
        $this->creditMemoRepository = $creditMemoRepository;
        $this->storeRepository = $storeRepository;
        $this->json = $json;
    }

    /**
     * Get Orders
     *
     * @return mixed|string[]
     */
    public function getOrders()
    {
        $status = $this->request->getParam("status") ?: [
            "pending",
            "processing",
            "complete",
            "holded",
            "pending_payment",
        ];
        $page = (int)$this->request->getParam("page") ?: 1;
        $limit = (int)$this->request->getParam("limit") ?: 200;
        $createdAtMin = $this->request->getParam("CreatedAtMin");
        $createdAtMax = $this->request->getParam("CreatedAtMax");

        $orderCollection = $this->orderCollectionFactory->create();

        if ($createdAtMin && $createdAtMax) {
            $orderCollection->addAttributeToFilter("created_at", [
                "from" => $createdAtMin,
                "to" => $createdAtMax,
            ]);
        }
        $orderCollection->addFieldToFilter("status", ["in" => $status]);
        $orderCollection->setPageSize($limit);
        $orderCollection->setCurPage($page);
        $orderData = [];
        foreach ($orderCollection as $order) {
            $apiUrl = $this->request->getUriString();
            $storeCodeApi = $this->helper->getStoreCodeByApi($apiUrl);
            $storeCode = $this->storeRepository->get($order->getStoreId())->getCode();
            if ($storeCodeApi && $storeCodeApi !== $storeCode) {
                continue;
            }
            $orderData[] = $this->getOrderData($order);
        }

        $totalOrders = $orderCollection->getSize();

        $pageInfo = [
            "current_page" => $page,
            "per_page" => $limit,
            "total_count" => $totalOrders,
            "total_pages" => ceil($totalOrders / $limit),
        ];
        return $this->helper->getResponseStatus(
            __("Success"),
            200,
            true,
            $orderData,
            $pageInfo,
            $nestedArray = true
        );
    }

    /**
     * Get order data.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     * @throws LocalizedException
     */
    public function getOrderData($order)
    {
        $extensionAttributes = [];
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
                    "track_number" => (string)$data["tracking_number"],
                    "title" => (string)$data["title"],
                    "carrier_code" => (string)$data["code"],
                    "tracing_link" => (string)$data["tracing_link"],
                    "tracking_number" => (string)$data["tracking_number"],
                    "shipping_label_pdf" =>
                        (string)$data["shipping_label_pdf"],
                ];
            }

            $customerData = [
                "customer_id" => (string)$order
                    ->getCustomerId(),
                "first_name" => (string)$order
                    ->getBillingAddress()
                    ->getFirstName(),
                "last_name" => (string)$order
                    ->getBillingAddress()
                    ->getLastName(),
                "email" => (string)$order->getBillingAddress()->getEmail(),
                "phone" => (string)$order->getBillingAddress()->getTelephone(),
                "company" => (string)$order->getBillingAddress()->getCompany(),
                "address_1" => (string)$order
                    ->getBillingAddress()
                    ->getStreetLine1(),
                "address_2" => (string)$order
                    ->getBillingAddress()
                    ->getStreetLine2(),
                "city" => (string)$order->getBillingAddress()->getCity(),
                "state" => (string)$order->getBillingAddress()->getRegion(),
                "postcode" => (string)$order
                    ->getBillingAddress()
                    ->getPostcode(),
                "country" => (string)$order
                    ->getBillingAddress()
                    ->getCountryId(),
            ];
            foreach ($order->getItems() as $item) {
                $product = $item->getProduct();
                if ($product = $item->getProduct()) {
                    $orderItems[] = [
                        "id" => (int)$item->getQuoteItemId(),
                        "sku" => (string)$product->getSku(),
                        "product_id" => (int)$product->getId(),
                        "name" => (string)$product->getName(),
                        "barcode" => $product->getCustomAttribute(
                            "omniful_barcode_attribute"
                        )
                            ? (string)$product
                                ->getCustomAttribute("omniful_barcode_attribute")
                                ->getValue()
                            : null,
                        "quantity" => (float)$item->getQtyOrdered(),
                        "price" => (float)$item->getPrice(),
                        "original_price" => (float)$item->getOriginalPrice(),
                        "price_incl_tax" => (float)$item->getPriceInclTax(),
                        "discount_amount" => (float)$item->getDiscountAmount(),
                        "subtotal" => (float)$item->getRowTotal(),
                        "total" => (float)$item->getRowTotalInclTax(),
                        "tax" => (float)$item->getTaxAmount(),
                    ];
                }
            }

            $invoiceFullData = $this->getInvoiceDetails($order);
            $creditMemosFullData = $this->getCreditMemosCollection($order);
            $invoiceData = [
                "currency" => (string)$order->getOrderCurrencyCode(),
                "subtotal" => (float)$order->getSubtotal(),
                "shipping_price" => (float)$order->getShippingAmount(),
                "tax" => (float)$order->getTaxAmount(),
                "discount" => (float)$order->getDiscountAmount(),
                "total" => (float)$order->getGrandTotal(),
            ];

            $paymentMethod = [
                "code" => (string)$order->getPayment()->getMethod(),
                "title" => (string)$order
                    ->getPayment()
                    ->getMethodInstance()
                    ->getTitle(),
                "is_cash_on_delivery" => $this->isCashOnDelivery($order),
            ];

            $shippingAddress = $this->getShippingAddressData(
                $customerId,
                $order->getShippingAddress()->getData()
            );

            // Retrieve totals
            $totals = [
                "subtotal" => [
                    "title" => __("Subtotal"),
                    "value" => (float)$order->getSubtotal(),
                    "formatted_value" => strip_tags(
                        $order->formatPrice($order->getSubtotal())
                    ),
                ],
                "shipping" => [
                    "title" => __("Shipping"),
                    "value" => (float)$order->getShippingAmount(),
                    "formatted_value" => strip_tags(
                        $order->formatPrice($order->getShippingAmount())
                    ),
                ],
                "tax" => [
                    "title" => __("Tax"),
                    "value" => (float)$order->getTaxAmount(),
                    "formatted_value" => strip_tags(
                        $order->formatPrice($order->getTaxAmount())
                    ),
                ],
                "discount" => [
                    "title" => __("Discount"),
                    "value" => (float)$order->getDiscountAmount(),
                    "formatted_value" => strip_tags(
                        $order->formatPrice($order->getDiscountAmount())
                    ),
                ],
                "total" => [
                    "title" => __("Total"),
                    "value" => (float)$order->getGrandTotal(),
                    "formatted_value" => strip_tags(
                        $order->formatPrice($order->getGrandTotal())
                    ),
                ],
                "total_refunded" => [
                    "title" => __("Total Refunded"),
                    "value" => (float)$order->getTotalRefunded(),
                    "formatted_value" => strip_tags(
                        $order->formatPrice($order->getTotalRefunded())
                    ),
                ],
                "total_paid" => [
                    "title" => __("Total Paid"),
                    "value" => (float)$order->getTotalPaid(),
                    "formatted_value" => strip_tags(
                        $order->formatPrice($order->getTotalPaid())
                    ),
                ],
                "total_due" => [
                    "title" => __("Total Due"),
                    "value" => (float)$order->getTotalDue(),
                    "formatted_value" => strip_tags(
                        $order->formatPrice($order->getTotalDue())
                    ),
                ],
            ];

            $allowedExtensionAttributes = ["order_custom_attributes"];
            $data = $this->getOrderJsonData($order->getId());
            $serializedArray = $this->json->serialize((array)$data->getExtensionAttributes());
            $unserializedArray = $this->json->unserialize($serializedArray);
            $extensionAttributes = array_values($unserializedArray)[0];
            unset($extensionAttributes['payment_additional_info']);
            unset($extensionAttributes['shipping_assignments']);
            unset($extensionAttributes['applied_taxes']);
            unset($extensionAttributes['item_applied_taxes']);
            foreach ($extensionAttributes as $key => $extensionAttribute) {
                if (is_array($extensionAttribute)) {
                    $functionName = "get" . $this->camelize($key);
                    $functionData = $data->getExtensionAttributes()->$functionName();
                    if (is_object($functionData)) {
                        $extensionAttributes[$key] = $this->dismount($functionData);
                    } elseif (isset($functionData[0]) && is_object($functionData[0])) {
                        $extensionAttributes[$key] = $this->dismount($functionData[0]);
                    }
                }
            }

            $allowedAttributes = [];
            foreach ($allowedExtensionAttributes as $allowedExtensionAttribute) {
                if (isset($extensionAttributes[$allowedExtensionAttribute])) {
                    $allowedAttributes[$allowedExtensionAttribute] = $extensionAttributes[$allowedExtensionAttribute];
                }
            }
            return [
                "id" => (int)$order->getEntityId(),
                "increment_id" => $order->getIncrementId(),
                "status" => [
                    "code" => (string)$order->getStatus(),
                    "label" => $order->getStatusLabel(),
                    "state" => $order->getState(),
                ],
                "currency" => (string)$order->getOrderCurrencyCode(),
                "shipping_method" => (string)$order->getShippingMethod(),
                "total" => (float)$order->getGrandTotal(),
                "subtotal" => (float)$order->getSubtotal(),
                "tax_applied_on_shipping" => (float)$order->getShippingTaxAmount() ? true : false,
                "shipping_tax_amount" => (float)$order->getShippingTaxAmount(),
                "tax_total" => (float)$order->getTaxAmount(),
                "discount_total" => (float)$order->getDiscountAmount(),
                "created_at" => $order->getCreatedAt()
                    ? $order->getCreatedAt()
                    : "",
                "invoice" => $invoiceData,
                "invoice_data" => $invoiceFullData,
                "credit_memo_data" => $creditMemosFullData,
                "customer" => $customerData,
                "order_items" => $orderItems,
                "payment_method" => $paymentMethod,
                "shipping_address" => $shippingAddress,
                "cancel_reason" => $this->getCancelReason($order),
                "totals" => $totals,
                "shipments" => $shipmentTracking,
                'extension_attributes' => $allowedAttributes
            ];
        } catch (NoSuchEntityException $e) {
            return $this->helper->getResponseStatus(
                __("Order not found."),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Get Invoice Data
     *
     * @param mixed $order
     * @return array
     */
    public function getInvoiceDetails($order)
    {
        $invoices = $order->getInvoiceCollection();
        $invoiceData = [];
        foreach ($invoices as $invoice) {
            $invoice = $this->invoiceManagement->load($invoice->getId());
            $invoiceData[] = $invoice->debug();
        }
        return $invoiceData;
    }

    /**
     * Get Credit Memos Collection
     *
     * @param mixed $order
     * @return array
     */
    public function getCreditMemosCollection($order)
    {
        $creditMemos = $order->getCreditmemosCollection();
        $creditMemoData = [];

        // Check if $creditMemos is not false before iterating
        if ($creditMemos !== false) {
            foreach ($creditMemos as $creditMemo) {
                $creditMemo = $this->creditMemoRepository->get($creditMemo->getId());
                $creditMemoData[] = $creditMemo->debug();
            }
        }

        return $creditMemoData;
    }

    /**
     * Check if the order payment method is cash on delivery
     *
     * @param OrderInterface $order
     * @return bool
     */
    protected function isCashOnDelivery($order)
    {
        return $order->getPayment()->getMethod() === "cashondelivery";
    }

    /**
     * Get Shipping Address Data
     *
     * @param mixed $customerId
     * @param mixed $address
     * @return mixed
     */
    public function getShippingAddressData($customerId, $address)
    {
        $country = $this->countriesHelper->getCountryByCode(
            $address["country_id"]
        );
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

    /**
     * Register entity to delete
     *
     * @param int
     * @return \Magento\Sales\Api\Data\OrderInterface $entity
     */
    public function getOrderJsonData($orderId)
    {
        return $this->getOrderByIdentifier($orderId);
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
            throw new NoSuchEntityException(__("Order not found."));
        }

        return $order;
    }

    /**
     * Camelize
     *
     * @param mixed $input
     * @param string $separator
     * @return string
     */
    public function camelize($input, $separator = '_')
    {
        return ucfirst(str_replace($separator, '', ucwords($input, $separator)));
    }

    /**
     * Dismount
     *
     * @param mixed $object
     * @return array
     * @throws ReflectionException
     */
    public function dismount($object)
    {
        $reflectionClass = new ReflectionClass(get_class($object));
        $array = [];
        foreach ($reflectionClass->getProperties() as $property) {
            $property->setAccessible(true);
            $array[$property->getName()] = $property->getValue($object);
            $property->setAccessible(false);
        }
        return $array;
    }

    /**
     * Get cancel reason for order
     *
     * @param OrderInterface $order
     * @return string|null
     */
    protected function getCancelReason($order)
    {
        return $order->getData("omniful_cancel_reason") ?: null;
    }

    /**
     * Get Order By Id
     *
     * @param int|int $orderId
     * @return mixed|string[]
     * @throws NoSuchEntityException
     */
    public function getOrderById($orderId)
    {
        $order = $this->getOrderByIdentifier($orderId);
        if (!$order) {
            throw new NoSuchEntityException(__("Order not found."));
        }

        $orderData = $this->getOrderData($order);
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
        return $this->helper->getResponseStatus(
            __("Success"),
            200,
            true,
            $orderData,
            $pageData = null,
            $nestedArray = true
        );
    }
}
