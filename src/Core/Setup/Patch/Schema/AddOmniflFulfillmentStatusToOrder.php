<?php

namespace Omniful\Core\Setup\Patch\Schema;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\DB\Ddl\Table;

class AddOmniflFulfillmentStatusToOrder implements
    SchemaPatchInterface,
    PatchRevertableInterface
{
    private const COLUMN_NAME = "fulfillment_status";
    private const TABLE_NAME = "sales_order";
    private const GRID_TABLE_NAME = "sales_order_grid";
    private const COLUMN_DEFINITIONS = [
        "type" => Table::TYPE_TEXT,
        "nullable" => true,
        "length" => 255,
        "comment" => "Fulfillment Status",
    ];

    /**
     * ModuleDataSetupInterface
     *
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * AddOmniflFulfillmentStatusToOrder constructor.
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * Apply
     *
     * @return AddOmniflFulfillmentStatusToOrder|void
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();
        if (!$connection->tableColumnExists(
            $this->getTableName(),
            self::COLUMN_NAME
        )
        ) {
            $connection->addColumn(
                $this->getTableName(),
                self::COLUMN_NAME,
                self::COLUMN_DEFINITIONS
            );
        }

        if (!$connection->tableColumnExists(
            $this->getGridTableName(),
            self::COLUMN_NAME
        )
        ) {
            $connection->addColumn(
                $this->getGridTableName(),
                self::COLUMN_NAME,
                self::COLUMN_DEFINITIONS
            );
        }
        $this->moduleDataSetup->endSetup();
    }

    /**
     * Revert
     */
    public function revert()
    {
        $this->moduleDataSetup->startSetup();
        $connection = $this->moduleDataSetup->getConnection();
        if ($connection->tableColumnExists(
            $this->getTableName(),
            self::COLUMN_NAME
        )
        ) {
            $connection->dropColumn($this->getTableName(), self::COLUMN_NAME);
        }

        if ($connection->tableColumnExists(
            $this->getGridTableName(),
            self::COLUMN_NAME
        )
        ) {
            $connection->dropColumn(
                $this->getGridTableName(),
                self::COLUMN_NAME
            );
        }
        $this->moduleDataSetup->endSetup();
    }

    /**
     * GetTableName
     *
     * @return string
     */
    private function getTableName(): string
    {
        return $this->moduleDataSetup->getTable(self::TABLE_NAME);
    }

    /**
     * Get Grid Table Name
     *
     * @return string
     */
    private function getGridTableName(): string
    {
        return $this->moduleDataSetup->getTable(self::GRID_TABLE_NAME);
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
     * GetAliases
     *
     * @return array|string[]
     */
    public function getAliases()
    {
        return [];
    }
}
