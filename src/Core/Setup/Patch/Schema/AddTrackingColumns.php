<?php
declare(strict_types=1);

namespace Omniful\Core\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\PatchInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class AddTrackingColumns implements PatchInterface
{
    private const TABLE_NAME = "sales_shipment_track";
    private const COLUMN_TRACING_LINK = "tracing_link";
    private const COLUMN_SHIPPING_LABEL_PDF = "shipping_label_pdf";
    private const COLUMN_LENGTH = 255;
    private const COLUMN_COMMENT_TRACING_LINK = "Tracing Link";
    private const COLUMN_COMMENT_SHIPPING_LABEL_PDF = "Shipping Label PDF";

    /**
     * @var SchemaSetupInterface
     */
    private $schemaSetup;

    /**
     * @param SchemaSetupInterface $schemaSetup
     */
    public function __construct(SchemaSetupInterface $schemaSetup)
    {
        $this->schemaSetup = $schemaSetup;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->schemaSetup->startSetup();
        $connection = $this->schemaSetup->getConnection();
        $table = $this->schemaSetup->getTable(self::TABLE_NAME);

        $connection->addColumn($table, self::COLUMN_TRACING_LINK, [
            "type" => Table::TYPE_TEXT,
            "length" => self::COLUMN_LENGTH,
            "nullable" => true,
            "comment" => self::COLUMN_COMMENT_TRACING_LINK,
        ]);

        $connection->addColumn($table, self::COLUMN_SHIPPING_LABEL_PDF, [
            "type" => Table::TYPE_TEXT,
            "length" => self::COLUMN_LENGTH,
            "nullable" => true,
            "comment" => self::COLUMN_COMMENT_SHIPPING_LABEL_PDF,
        ]);

        $this->schemaSetup->endSetup();
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
}
