<?php

namespace Omniful\Core\Model\ReturnOrder;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Rma\Api\RmaAttributesManagementInterface as RmaAttributeRepositoryInterface;
use Magento\Rma\Model\ResourceModel\Rma\CollectionFactory;
use Magento\Rma\Model\Rma\Status\History;
use Magento\Rma\Model\ShippingFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Omniful\Core\Api\ReturnOrder\RmaRepositoryInterface;
use Omniful\Core\Api\Sales\OrderInterface;
use Omniful\Core\Helper\Countries;
use Omniful\Core\Helper\Data;
use Omniful\Core\Model\Sales\Shipment as ShipmentManagement;

class Rma implements RmaRepositoryInterface
{
    public const STATE_PENDING = 'pending';
    public const STATE_AUTHORIZED = 'authorized';
    public const STATE_APPROVED = 'approved';
    public const STATE_CLOSED = 'closed';
    /**
     * @var \Magento\Rma\Api\RmaRepositoryInterface
     */
    public $rmaRepository;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var ShipmentManagement
     */
    private $shipmentManagement;
    /**
     * @var Countries
     */
    private $countriesHelper;
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var CollectionFactory
     */
    private $rmaFactory;
    /**
     * @var History
     */
    private $history;
    /**
     * @var \Magento\Rma\Model\ResourceModel\Item\CollectionFactory
     */
    private $rmaItemCollection;
    /**
     * @var ShippingFactory
     */
    private $shippingLabelFactory;
    /**
     * @var RmaAttributeRepositoryInterface
     */
    private $rmaAttributeRepository;
    /**
     * @var RestRequest
     */
    private $restRequest;
    /**
     * @var StoreRepositoryInterface
     */
    private $storeRepository;
    /**
     * @var AttributeOptionInterfaceFactory
     */
    private $optionFactory;
    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory
     */
    private $attributeOptionCollection;

    /**
     * Rma constructor.
     *
     * @param \Magento\Rma\Api\RmaRepositoryInterface $rmaRepository
     * @param ShipmentManagement $shipmentManagement
     * @param Countries $countriesHelper
     * @param Data $helper
     * @param RequestInterface $request
     * @param History $history
     * @param ProductRepositoryInterface $productRepository
     * @param CollectionFactory $rmaFactory
     * @param \Magento\Rma\Model\ResourceModel\Item\CollectionFactory $rmaItemCollection
     * @param ShippingFactory $shippingLabelFactory
     * @param RestRequest $restRequest
     * @param AttributeOptionInterfaceFactory $optionFactory
     * @param \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $attributeOptionCollection
     * @param StoreRepositoryInterface $storeRepository
     * @param RmaAttributeRepositoryInterface $rmaAttributeRepository
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        \Magento\Rma\Api\RmaRepositoryInterface $rmaRepository,
        ShipmentManagement $shipmentManagement,
        Countries $countriesHelper,
        Data $helper,
        RequestInterface $request,
        History $history,
        ProductRepositoryInterface $productRepository,
        CollectionFactory $rmaFactory,
        \Magento\Rma\Model\ResourceModel\Item\CollectionFactory $rmaItemCollection,
        ShippingFactory $shippingLabelFactory,
        RestRequest $restRequest,
        AttributeOptionInterfaceFactory $optionFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $attributeOptionCollection,
        StoreRepositoryInterface $storeRepository,
        RmaAttributeRepositoryInterface $rmaAttributeRepository,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->rmaRepository = $rmaRepository;
        $this->orderRepository = $orderRepository;

        $this->productRepository = $productRepository;
        $this->shipmentManagement = $shipmentManagement;
        $this->countriesHelper = $countriesHelper;
        $this->helper = $helper;
        $this->request = $request;
        $this->rmaFactory = $rmaFactory;
        $this->history = $history;
        $this->rmaItemCollection = $rmaItemCollection;
        $this->shippingLabelFactory = $shippingLabelFactory;
        $this->rmaAttributeRepository = $rmaAttributeRepository;
        $this->restRequest = $restRequest;
        $this->storeRepository = $storeRepository;
        $this->optionFactory = $optionFactory;
        $this->attributeOptionCollection = $attributeOptionCollection;
    }

    /**
     * Get Return Order Data By Id
     *
     * @param int|int $id
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getReturnOrderDataById($id)
    {
        $rma = $this->rmaRepository->get($id);
        if (!$rma) {
            throw new NoSuchEntityException(__("Order not found."));
        }
        $rmaData = $this->getReturnOrderData($rma);
        $apiUrl = $this->request->getUriString();
        $storeCodeApi = $this->helper->getStoreCodeByApi($apiUrl);
        $storeCode = $this->storeRepository->get($rma->getStoreId())->getCode();
        if ($storeCodeApi && $storeCodeApi !== $storeCode) {
            return $this->helper->getResponseStatus(
                __("Return not found."),
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
            $rmaData,
            $pageData = null,
            $nestedArray = true
        );
    }

    /**
     * Get Return Order Data
     *
     * @param mixed $rma
     * @return mixed|void
     */
    public function getReturnOrderData($rma)
    {
        $order = $this->orderRepository->get($rma->getOrderId());

        $orderItems = [];
        $shipmentTracking = [];
        $customerData = [];
        $paymentMethod = [];
        $shippingAddress = [];
        try {
            $customerId = $order->getCustomerId();
            $customerData = [
                "customer_id" => $customerId,
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

            foreach ($rma->getItems() as $item) {
                $rmaAttributes = $this->getResolutionAttributeValue($item, $orderItems);
                $product = $this->getProductBySku($item->getProductSku());
                $orderItems[] = array_merge([
                    "id" => (int)$item->getId(),
                    "sku" => (string)$product->getSku(),
                    "product_id" => (int)$product->getId(),
                    "name" => (string)$product->getName(),
                    "quantity" => (float)$item->getQtyRequested(),
                ], $rmaAttributes);
            }
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
            $dataReturn = [
                'rma_id' => (int)$rma->getEntityId(),
                'rma_increment_id' => $rma->getIncrementId(),
                "status" => $rma->getStatus(),
                "order_id" => $rma->getOrderId(),
                "order_increment_id" => $rma->getOrderIncrementId(),
                "store_id" => $rma->getStoreId(),
                "customer_id" => $rma->getCustomerId(),
                "currency" => (string)$order->getOrderCurrencyCode(),
                "customer" => $customerData,
                "return_items" => $orderItems,
                "payment_method" => $paymentMethod,
                "shipping_address" => $shippingAddress,
            ];
            foreach ($rma->getComments() as $comment) {
                $dataReturn['comment'][] = $comment->debug();
            }
            foreach ($rma->getTracks() as $tracks) {
                $dataReturn['shipments'][] = $tracks->debug();
            }

            return $dataReturn;
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
     * Get Resolution Attribute Value
     *
     * @param mixed $item
     * @param mixed $orderItems
     */
    public function getResolutionAttributeValue($item, $orderItems)
    {
        $rmaAttributes = [];
        $attributes = $this->rmaAttributeRepository->getAllAttributesMetadata();
        $attributeCodes = [];
        foreach ($attributes as $attribute) {
            $options = $attribute->__toArray();
            $attributeCodes[] = $options['attribute_code'];
        }
        $updateData = [];
        foreach ($attributeCodes as $attributeCode) {
            $updateData[$attributeCode] = $item[$attributeCode];
        }
        foreach ($updateData as $key => $optionValue) {
            $optionFactory = $this->optionFactory->create();
            $optionFactory->load($optionValue);
            $attributeId = $optionFactory->getAttributeId();
            $optionData = $this->attributeOptionCollection->create()->setAttributeFilter($attributeId)
                ->setIdFilter($optionValue)
                ->setStoreFilter()
                ->load();
            foreach ($optionData as $option) {
                if ($updateData[$key] == $option->getOptionId()) {
                    $rmaAttributes[$key] = $option->getValue();
                }
            }
        }
        return $rmaAttributes;
    }

    /**
     * Get Product By Sku
     *
     * @param string $sku
     * @return ProductInterface
     * @throws NoSuchEntityException
     */
    public function getProductBySku($sku)
    {
        return $this->productRepository->get($sku);
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
     * Get Return Order
     *
     * @return mixed
     */
    public function getReturnOrder()
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
        $orderCollection = $this->rmaFactory->create();
        if ($createdAtMin && $createdAtMax) {
            $orderCollection->addAttributeToFilter("date_requested", [
                "from" => $createdAtMin,
                "to" => $createdAtMax,
            ]);
        }
        $orderCollection->addFieldToFilter("status", ["in" => $status]);
        if ($orderId = $this->request->getParam("orderId")) {
            if (is_numeric($orderId)) {
                $orderCollection
                    ->addFieldToFilter("status", ["in" => $status])
                    ->addFieldToFilter("order_id", ["in" => $orderId]);
            } else {
                $orderCollection
                    ->addFieldToFilter("status", ["in" => $status])
                    ->addFieldToFilter("order_increment_id", ["in" => $orderId]);
            }
        }
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
            $orderData[] = $this->getReturnOrderData($order);
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
     * Update Rma Status
     *
     * @param int $id
     * @param string $status
     * @param string|null $comment
     * @return mixed|void
     */
    public function updateRmaStatus(
        int $id,
        string $status,
        string $comment = null
    ) {
        try {
            if ($status === self::STATE_PENDING ||
                $status === self::STATE_AUTHORIZED ||
                $status === self::STATE_APPROVED ||
                $status === self::STATE_CLOSED
            ) {
                $visible = isset($data['is_visible_on_front']);
                $rma = $this->rmaRepository->get($id);
                $rma->setStatus($this->getRmaStatus($status));
                if ($comment !== null && $comment !== "") {
                    $history = $this->history;
                    $history->setRmaEntityId($rma->getId());
                    $history->setComment($comment);
                    $history->saveComment($comment, $visible, true);
                }
                $rma->save();
                $rmaData = $this->getReturnOrderData($rma);
                return $this->helper->getResponseStatus(
                    __("Success"),
                    200,
                    true,
                    $rmaData,
                    $pageData = null,
                    $nestedArray = true
                );
            } else {
                return $this->helper->getResponseStatus(
                    __("This status not valid for update status"),
                    500,
                    false,
                    $data = null,
                    $pageData = null,
                    $nestedArray = true
                );
            }
        } catch (Exception $e) {
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
    protected function getRmaStatus(string $status): string
    {
        switch ($status) {
            case "authorized":
                return self::STATE_AUTHORIZED;
            case "approved":
                return self::STATE_APPROVED;
            case "closed":
                return self::STATE_CLOSED;
            case "pending":
            default:
                return self::STATUS_NEW;
        }
    }

    /**
     * Return approve
     *
     * @param mixed $rmaEntityId
     * @param array $returnApprove
     * @return mixed
     */
    public function approveRma(
        $rmaEntityId,
        $returnApprove = []
    ) {
        try {
            $approveQuantity = [];
            if ($returnApprove) {
                foreach ($returnApprove as $item) {
                    $approveQuantity[$item['id']] = $item['quantity'];
                }
            }
            $bodyParams = $this->restRequest->getBodyParams();
            $rma = $this->rmaRepository->get($bodyParams['rma_entity_id']);
            if ($rma->getStatus() == "Closed") {
                return $this->helper->getResponseStatus(
                    __("You can't change. This return is Closed"),
                    200,
                    true,
                    $pageData = null,
                    $nestedArray = true
                );
            }
            $rmaItemCollectionFactory = $this->rmaItemCollection;
            $rmaItemsCollection = $rmaItemCollectionFactory->create()
                ->addFieldToFilter('rma_entity_id', $rmaEntityId);
            $rmaItemData = [];
            $status = [];
            foreach ($rmaItemsCollection as $rmaItem) {
                $requestedQty = $rmaItem->getQtyRequested();
                $qty = isset($approveQuantity[$rmaItem->getEntityId()]);
                if ($qty) {
                    if (isset($approveQuantity[$rmaItem->getEntityId()])) {
                        $updatedQty = (int)$rmaItem->getQtyApproved() + (int)$approveQuantity[$rmaItem->getEntityId()];
                        if ((int)$requestedQty >= $updatedQty) {
                            $rmaItem->setQtyApproved($updatedQty);
                            $rmaItem->setStatus('approved');
                            $rmaItem->save();
                        } else {
                            $rmaItem->setQtyApproved($requestedQty);
                            $rmaItem->setStatus('approved');
                            $rmaItem->save();
                        }
                        $rmaItemData[] = $rmaItem->debug();
                    }
                } else {
                    $rmaItem->setQtyApproved($requestedQty);
                    $rmaItem->setStatus('approved');
                    $rmaItem->save();
                    $rmaItemData[] = $rmaItem->debug();
                }
                $status[] = $rmaItem->getStatus();
            }
            if (count(array_unique($status)) == 1) {
                $rma->setStatus("Processed and Closed");
                $rma->save();
            } else {
                $rma->setStatus("Partially Approved");
                $rma->save();
            }
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $rmaItemData,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __("Status Not Approved" . $e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Reject Rma
     *
     * @param mixed $rmaEntityId
     * @param mixed $returnReject
     * @return mixed|void
     */
    public function rejectRma(
        $rmaEntityId,
        $returnReject = []
    ) {
        try {
            $rejectId = [];
            if ($returnReject) {
                foreach ($returnReject as $item) {
                    $rejectId[] = $item['id'];
                }
            }
            $rmaItemCollectionFactory = $this->rmaItemCollection;
            $rmaItemsCollection = $rmaItemCollectionFactory->create()
                ->addFieldToFilter('rma_entity_id', $rmaEntityId);
            $rmaItemData = [];
            $status = [];
            foreach ($rmaItemsCollection as $rmaItem) {
                if ($rejectId) {
                    if (in_array($rmaItem->getEntityId(), $rejectId)) {
                        $rmaItem->setStatus('rejected');
                        $rmaItem->save();
                        $rmaItemData[] = $rmaItem->debug();
                    }
                } else {
                    $rmaItem->setStatus('rejected');
                    $rmaItem->save();
                    $rmaItemData[] = $rmaItem->debug();
                }
                $status[] = $rmaItem->getStatus();
            }
            if (count(array_unique($status)) == 1) {
                $bodyParams = $this->restRequest->getBodyParams();
                $rma = $this->rmaRepository->get($bodyParams['rma_entity_id']);
                $rma->setStatus("Closed");
                $rma->save();
            } else {
                $bodyParams = $this->restRequest->getBodyParams();
                $rma = $this->rmaRepository->get($bodyParams['rma_entity_id']);
                $rma->setStatus("Partially Rejected");
                $rma->save();
            }
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $rmaItemData,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __("Status Not Approved" . $e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Update Shipment
     *
     * @param string|null $rmaEntityId
     * @param string|null $carrier
     * @param string|null $title
     * @param string|null $trackingNumber
     * @param string|null $shippingLabel
     * @param string|null $packages
     * @param string|null $methodTitle
     * @param string|null $methodCode
     * @param string|null $price
     * @return mixed
     */
    public function updateShipment(
        $rmaEntityId = null,
        $carrier = null,
        $title = null,
        $trackingNumber = null,
        $shippingLabel = null,
        $packages = null,
        $methodTitle = null,
        $methodCode = null,
        $price = null
    ) {
        try {
            $rmaShippingLabel = $this->shippingLabelFactory->create();
            $rmaShippingLabel->setRmaEntityId($rmaEntityId);
            $rmaShippingLabel->setCarrierCode($carrier);
            $rmaShippingLabel->setCarrierTitle($title);
            $rmaShippingLabel->setTrackNumber($trackingNumber);
            $rmaShippingLabel->save();
            return $this->helper->getResponseStatus(
                __("Shipment updated"),
                200,
                true,
                $rmaShippingLabel->debug(),
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __("Shipment Not update" . $e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Get return attributes data
     *
     * @return array
     */
    public function getReturnAttributes()
    {
        try {
            // Implement the logic to retrieve return attribute data
            $attributes = $this->rmaAttributeRepository->getAllAttributesMetadata();
            $returnAttributes = [];
            foreach ($attributes as $attribute) {
                $options = $attribute->__toArray();
                $optionArray = $options['options'];
                if (isset($optionArray) && empty($optionArray[0]['value'])) {
                    unset($optionArray[0]);
                }
                $returnAttributes[] = [
                    'attribute_code' => $attribute->getAttributeCode(),
                    'label' => $attribute->getFrontendLabel(),
                    'option' => $optionArray,
                ];
            }
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $returnAttributes,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __("Attribute Not Found" . $e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }

    /**
     * Reject Rma
     *
     * @param mixed $rmaEntityId
     * @param mixed $returnAuthorize
     * @return mixed|void
     */
    public function authorizeRma(
        $rmaEntityId,
        $returnAuthorize = []
    ) {
        try {
            $authorizeQuantity = [];
            if ($returnAuthorize) {
                foreach ($returnAuthorize as $item) {
                    $authorizeQuantity[$item['id']] = $item['quantity'];
                }
            }
            $bodyParams = $this->restRequest->getBodyParams();
            $rma = $this->rmaRepository->get($bodyParams['rma_entity_id']);
            if ($rma->getStatus() == "Closed" || $rma->getStatus() == "Processed and Closed") {
                return $this->helper->getResponseStatus(
                    __("You can't change. This return is Closed"),
                    200,
                    true,
                    $pageData = null,
                    $nestedArray = true
                );
            }
            $rmaItemCollectionFactory = $this->rmaItemCollection;
            $rmaItemsCollection = $rmaItemCollectionFactory->create()
                ->addFieldToFilter('rma_entity_id', $rmaEntityId);
            $rmaItemData = [];
            $status = [];
            foreach ($rmaItemsCollection as $rmaItem) {
                $requestedQty = $rmaItem->getQtyRequested();
                $qty = isset($authorizeQuantity[$rmaItem->getEntityId()]);

                if ($qty) {
                    if (isset($authorizeQuantity[$rmaItem->getEntityId()])) {
                        $updatedQty = (int)$rmaItem->getQtyAuthorized() + (int)$authorizeQuantity[$rmaItem->getEntityId()];
                        if ((int)$requestedQty >= $updatedQty) {
                            $rmaItem->setQtyAuthorized($updatedQty);
                            $rmaItem->setStatus('authorized');
                            $rmaItem->save();
                        } else {
                            $rmaItem->setQtyAuthorized($requestedQty);
                            $rmaItem->setStatus('authorized');
                            $rmaItem->save();
                        }
                        $rmaItemData[] = $rmaItem->debug();
                    }
                } else {
                    $rmaItem->setQtyAuthorized($requestedQty);
                    $rmaItem->setStatus('authorized');
                    $rmaItem->save();
                    $rmaItemData[] = $rmaItem->debug();
                }
                $status[] = $rmaItem->getStatus();
            }
            if (count(array_unique($status)) == 1) {
                $rma->setStatus("authorized");
                $rma->save();
            } else {
                $rma->setStatus("partially_authorized");
                $rma->save();
            }
            return $this->helper->getResponseStatus(
                __("Success"),
                200,
                true,
                $rmaItemData,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __("Status Not Approved" . $e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }
}
