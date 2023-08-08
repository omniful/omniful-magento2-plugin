<?php

namespace Omniful\Core\Ui\Component\Listing\Column;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Ui\Component\Listing\Columns\Column;

class FulfillmentStatus extends Column
{
    /**
     * @var OrderRepository
     */
    protected $orderRepository;

    /**
     * FulfillmentStatus constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param OrderRepository $orderRepository
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        OrderRepository $orderRepository,
        array $components = [],
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param  array $dataSource
     * @return array
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource["data"]["items"])) {
            foreach ($dataSource["data"]["items"] as &$item) {
                $orderId = $item["entity_id"];
                $order = $this->orderRepository->get($orderId);
                $fulfillmentStatus = $order->getFulfillmentStatus();
                $item[$this->getData("name")] = $fulfillmentStatus;
            }
        }
        return $dataSource;
    }
}
