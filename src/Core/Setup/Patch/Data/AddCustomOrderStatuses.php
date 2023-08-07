<?php

namespace Omniful\Core\Setup\Patch\Data;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Status;
use Magento\Sales\Model\Order\StatusFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\ResourceModel\Order\StatusFactory as StatusResourceFactory;
use Magento\Sales\Model\ResourceModel\Status\CollectionFactory;

class AddCustomOrderStatuses implements DataPatchInterface
{
    /**
     * Custom Order-Status.
     */

    public const PACKED = "packed";
    public const SHIPPED = "shipped";
    public const REFUNDED = "refunded";
    public const DELIVERED = "delivered";
    public const READY_TO_SHIP = "ready_to_ship";
    /**
     * @var StatusFactory
     */
    protected $statusFactory;
    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;
    /**
     * @var CollectionFactory
     */
    protected $statusCollection;
    /**
     * @var StatusResourceFactory
     */
    protected $statusResourceFactory;

    /**
     * AddCustomOrderStatuses constructor.
     *
     * @param StatusFactory $statusFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param StatusResourceFactory $statusResourceFactory
     * @param CollectionFactory $statusCollection
     */
    public function __construct(
        StatusFactory $statusFactory,
        ModuleDataSetupInterface $moduleDataSetup,
        StatusResourceFactory $statusResourceFactory,
        CollectionFactory $statusCollection
    ) {
        $this->statusFactory = $statusFactory;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->statusCollection = $statusCollection;
        $this->statusResourceFactory = $statusResourceFactory;
    }

    /**
     * Apply
     *
     * @return AddCustomOrderStatuses|void
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
            return __($e->getMessage());
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * Add New Order State And Status
     *
     * @param string $code
     * @param string $label
     * @param string $state
     * @param string $sortOrder
     * @param bool   $isDefault
     */
    protected function addNewOrderStateAndStatus(
        $code,
        $label,
        $state,
        $sortOrder,
        $isDefault = false
    ) {
        try {
            $statusResource = $this->statusResourceFactory->create();
            /**
             * @var Status $status
             */
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
     * Get Dependencies
     *
     * @return array|string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Get Aliases
     *
     * @return array|string[]
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * To KebabCase
     *
     * @param  string $value
     * @return string
     */
    public function toKebabCase($value)
    {
        $value = str_replace("_", " ", $value);

        return ucwords($value);
    }
}
