<?php

namespace Omniful\Core\Observer\Sales;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Logger\Logger;
use Omniful\Core\Model\Adapter;

class OrderCreditMemoAfter implements ObserverInterface
{
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditMemoRepository;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var Adapter
     */
    private $adapter;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * OrderCreditMemoAfter constructor.
     *
     * @param OrderFactory $orderFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Logger $logger
     * @param Adapter $adapter
     * @param StoreManagerInterface $storeManager
     * @param CreditmemoRepositoryInterface $creditMemoRepository
     */
    public function __construct(
        OrderFactory $orderFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Logger $logger,
        Adapter $adapter,
        StoreManagerInterface $storeManager,
        CreditmemoRepositoryInterface $creditMemoRepository
    ) {
        $this->orderFactory = $orderFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->creditMemoRepository = $creditMemoRepository;
        $this->logger = $logger;
        $this->adapter = $adapter;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute
     *
     * @param Observer $observer
     * @return string|void
     */
    public function execute(Observer $observer)
    {
        try {
            $creditMemo = $observer->getEvent()->getCreditmemo();
            $orderId = $observer->getEvent()->getCreditmemo()->getOrderId();
            $order = $creditMemo->getOrder();
            if (!$order->getIsVirtual()) {
                // CONNECT FIRST
                $this->adapter->connect();
                // PUSH Refund ORDER EVENT
                $this->logger->info(__("Order Refund successfully"));
                return $this->refundData($creditMemo, $orderId);
            }
        } catch (Exception $e) {
            return __($e->getMessage());
        }
    }

    /**
     * Refund Data
     *
     * @param mixed $refund
     * @param mixed $orderId
     * @throws NoSuchEntityException
     */
    public function refundData($refund, $orderId)
    {
        if ($refund->getTotalQty() > 0) {
            $order = $this->orderFactory->create()->load($orderId);
            $store = $order->getStore();
            $storeData = $this->storeManager->getGroup($store->getGroupId());
            $headers = [
                "website-code" => $order
                    ->getStore()
                    ->getWebsite()
                    ->getCode(),
                "x-store-code" => $storeData->getCode(),
                "x-store-view-code" => $order->getStore()->getCode(),
            ];
            if (!$order->canCreditmemo() && $order->getCreditmemosCollection()->count() == 1) {
                /*For Full refund*/
                $searchCriteria = $this->searchCriteriaBuilder->addFilter('order_id', $orderId)->create();
                $refund = $this->creditMemoRepository->getList($searchCriteria);
                $refundsItems = $refund->toArray();
                $refundsUpdateItems = $refundsItems['items'][0];
                foreach ($refundsItems["items"] as $key => $refundItem) {
                    $refundData = $this->creditMemoRepository->get($refundItem["entity_id"]);
                    foreach ($refundData->getItems() as $item) {
                        unset($item['refund']);
                        $refundItemData = $item->debug();
                        $refundsUpdateItems['items'][] = $refundItemData;
                    }
                }
                return $this->adapter->publishMessage(
                    'order.full.refund',
                    $refundsUpdateItems,
                    $headers
                );
            } else {
                /*For Partial refund*/
                $refund = $refund->debug();
                $refundData["items"][0] = $refund;
                $itemData = $refund["items"];
                unset($refundData["items"][0]["items"]);
                $refundData = $this->managePartialRefundItems($itemData, $refundData['items'][0]);
                return $this->adapter->publishMessage(
                    'order.partial.refund',
                    $refundData,
                    $headers
                );
            }
        }
    }

    /**
     * Manage items and return updated items
     *
     * @param array $itemData
     * @param array $refundData
     * @return array
     */
    public function managePartialRefundItems($itemData, $refundData)
    {
        foreach ($itemData as $item) {
            if ($item["qty"]) {
                $item['qty'] = (int) $item['qty'];
                $refundData["items"][] = $item;
            }
        }
        return $refundData;
    }
}
