<?php

namespace Omniful\Core\Setup\Patch\Data;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;

class AddCustomOrderStatuses implements DataPatchInterface
{
    /**
     * Custom Order-Status.
     */

    const PACKED = "packed";
    const SHIPPED = "shipped";
    const REFUNDED = "refunded";
    const DELIVERED = "delivered";
    const READY_TO_SHIP = "ready_to_ship";

    protected $statusFactory;
    protected $moduleDataSetup;
    protected $statusCollection;
    protected $statusResourceFactory;

    public function __construct(
        StatusFactory $statusFactory,
        ModuleDataSetupInterface $moduleDataSetup,
        StatusResourceFactory $statusResourceFactory,
        \Magento\Sales\Model\ResourceModel\Status\CollectionFactory $statusCollection
    ) {
        $this->statusFactory = $statusFactory;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->statusCollection = $statusCollection;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $this->addNewOrderStateAndStatus(
            self::PACKED,
            $this->toKebabCase(self::PACKED),
            Order::STATE_PROCESSING,
            0
        );

        $this->addNewOrderStateAndStatus(
            self::READY_TO_SHIP,
            $this->toKebabCase(self::READY_TO_SHIP),
            Order::STATE_PROCESSING,
            1
        );

        $this->addNewOrderStateAndStatus(
            self::SHIPPED,
            $this->toKebabCase(self::SHIPPED),
            Order::STATE_COMPLETE,
            2
        );

        $this->addNewOrderStateAndStatus(
            self::DELIVERED,
            $this->toKebabCase(self::DELIVERED),
            Order::STATE_COMPLETE,
            3
        );

        $this->addNewOrderStateAndStatus(
            self::REFUNDED,
            $this->toKebabCase(self::REFUNDED),
            Order::STATE_CLOSED,
            4
        );

        // update processing status sort_order
        try {
            $status = $this->statusFactory
                ->create()
                ->load(Order::STATE_PROCESSING);
            $status->setSortOrder(2);
            $status->setIsBasic(1);
            $status->save();
        } catch (\Exception $e) {
            // Do nothing
        }

        $this->moduleDataSetup->endSetup();
    }

    protected function addNewOrderStateAndStatus(
        $code,
        $label,
        $state,
        $sortOrder,
        $isDefault = false
    ) {
        try {
            $statusResource = $this->statusResourceFactory->create();
            /** @var Status $status */
            $status = $this->statusFactory->create();
            $status->setData([
                "status" => $code,
                "label" => $label,
                "sort_order" => $sortOrder,
            ]);
            try {
                $statusResource->save($status);
            } catch (AlreadyExistsException $exception) {
                return;
            }
            $status->assignState($state, $isDefault, true);
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function toKebabCase($value)
    {
        $value = str_replace("_", " ", $value);

        return ucwords($value);
    }
}
